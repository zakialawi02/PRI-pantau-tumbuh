<?php

namespace App\Jobs;

use Throwable;
use App\Models\ImageryData;
use Illuminate\Bus\Queueable;
use App\Services\CreditService;
use App\Services\PythonService;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Queue\SerializesModels;
use Symfony\Component\Process\Process;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;

class ProcessSentinelClipJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public string $imageryId;
    public string $fieldAreaId;
    public array $payload;

    public function __construct(string $imageryId, string $fieldAreaId, array $payload = [])
    {
        $this->imageryId = $imageryId;
        $this->fieldAreaId = $fieldAreaId;
        $this->payload = $payload;
    }

    public function handle(): void
    {
        $imagery = ImageryData::find($this->imageryId);
        if (!$imagery) {
            Log::error('ProcessSentinelClipJob: Imagery record not found.', [
                'imagery_id' => $this->imageryId,
            ]);
            return;
        }

        $pythonService = new PythonService();
        $creditService = new CreditService();

        $publicStorage = storage_path('app/public');
        $imageryDir = $publicStorage . DIRECTORY_SEPARATOR . 'imagery';
        $clipDir = $imageryDir . DIRECTORY_SEPARATOR . 'clipped';
        if (!File::isDirectory($clipDir)) {
            File::makeDirectory($clipDir, 0755, true, true);
        }

        $outputFilename = $this->payload['output_filename'] ?? $imagery->stored_name;
        $outputPath = $clipDir . DIRECTORY_SEPARATOR . $outputFilename;
        $relativeOutput = 'storage/imagery/clipped/' . $outputFilename;

        $tilesDir = storage_path('app/tmp/sentinel_clip_' . $this->imageryId);
        if (!File::isDirectory($tilesDir)) {
            File::makeDirectory($tilesDir, 0755, true, true);
        }
        $mergedPath = $tilesDir . DIRECTORY_SEPARATOR . 'merged_fixed.tif';

        $scriptsBase = base_path('scripts');
        $scriptPath = $scriptsBase . DIRECTORY_SEPARATOR . 'process_clipped_multispectral_auto.py';
        $pythonPath = $pythonService->resolvePythonPath($scriptsBase);

        if (!$pythonPath) {
            Log::error('ProcessSentinelClipJob: Python executable not found for clipping process.');
            $creditService->refundCreditsForFailure($imagery, 'ClipJob');
            $imagery->update([
                'processing_status' => 'error',
                'upload_status' => 'failed',
            ]);
            return;
        }

        if (!File::exists($scriptPath)) {
            Log::error('ProcessSentinelClipJob: Clipping script not found.', [
                'path' => $scriptPath,
            ]);
            $creditService->refundCreditsForFailure($imagery, 'ClipJob');
            $imagery->update([
                'processing_status' => 'error',
                'upload_status' => 'failed',
            ]);
            return;
        }

        $geometry = $this->payload['geometry'] ?? null;
        if (!$geometry) {
            Log::error('ProcessSentinelClipJob: Geometry payload missing.', [
                'imagery_id' => $this->imageryId,
            ]);
            $creditService->refundCreditsForFailure($imagery, 'ClipJob');
            $imagery->update([
                'processing_status' => 'error',
                'upload_status' => 'failed',
            ]);
            return;
        }

        $dateFrom = $this->payload['date_from'] ?? now()->subMonth()->format('Y-m-d');
        $dateTo = $this->payload['date_to'] ?? now()->format('Y-m-d');
        $maxCloud = $this->payload['max_cloud'] ?? 60;
        $limit = $this->payload['limit'] ?? 50;
        $resolution = $this->payload['resolution'] ?? 10;
        $nodata = $this->payload['nodata'] ?? 0;

        $mode = $this->payload['mode'] ?? 'auto';
        $scene = $this->payload['scene'] ?? [];

        $imagery->update(['processing_status' => 'processing']);

        try {
            $overrides = [
                'SENTINEL_CLIP_GEOJSON' => json_encode([
                    'type' => 'FeatureCollection',
                    'features' => [
                        [
                            'type' => 'Feature',
                            'properties' => new \stdClass(),
                            'geometry' => $geometry,
                        ],
                    ],
                ], JSON_THROW_ON_ERROR),
                'SENTINEL_CLIP_DATE_FROM' => $dateFrom,
                'SENTINEL_CLIP_DATE_TO' => $dateTo,
                'SENTINEL_CLIP_MAX_CLOUD' => (string) $maxCloud,
                'SENTINEL_CLIP_LIMIT' => (string) $limit,
                'SENTINEL_CLIP_RESOLUTION' => (string) $resolution,
                'SENTINEL_CLIP_NODATA' => (string) $nodata,
                'SENTINEL_CLIP_TILE_DIR' => $tilesDir,
                'SENTINEL_CLIP_MERGED_PATH' => $mergedPath,
                'SENTINEL_CLIP_OUTPUT' => $outputPath,
            ];

            if ($mode === 'manual' && !empty($scene)) {
                if (!empty($scene['id'])) {
                    $overrides['SENTINEL_CLIP_SCENE_ID'] = (string) $scene['id'];
                }
                if (!empty($scene['datetime'])) {
                    $overrides['SENTINEL_CLIP_SCENE_DATETIME'] = (string) $scene['datetime'];
                }
            }

            $processEnv = $pythonService->buildProcessEnvironment($overrides);

            if (File::exists($outputPath)) {
                File::delete($outputPath);
            }

            $process = new Process([$pythonPath, $scriptPath], $scriptsBase, $processEnv);
            $process->setTimeout(7200);
            $process->run();

            $stdout = trim($process->getOutput());
            if ($stdout !== '') {
                Log::info('[Sentinel Clip STDOUT] ' . $stdout);
            }

            $stderr = trim($process->getErrorOutput());
            if ($stderr !== '') {
                Log::warning('[Sentinel Clip STDERR] ' . $stderr);
            }

            if (!$process->isSuccessful()) {
                throw new \RuntimeException('Sentinel clip processing failed.');
            }

            if (!File::exists($outputPath)) {
                throw new \RuntimeException('Clipped imagery output not found after processing.');
            }

            $size = File::size($outputPath) ?: 0;

            $imagery->update([
                'upload_status' => 'done',
                'processing_status' => 'completed',
                'format' => pathinfo($outputFilename, PATHINFO_EXTENSION) ?: 'tif',
                'size' => $size,
                'path' => $relativeOutput,
                'processed_path' => $relativeOutput,
                'processed_at' => now(),
            ]);

            Log::info('ProcessSentinelClipJob completed successfully.', [
                'imagery_id' => $this->imageryId,
                'output' => $relativeOutput,
            ]);
        } catch (Throwable $exception) {
            Log::error('ProcessSentinelClipJob failed.', [
                'imagery_id' => $this->imageryId,
                'error' => $exception->getMessage(),
            ]);

            $imagery->update([
                'processing_status' => 'error',
                'upload_status' => 'failed',
            ]);

            $creditService->refundCreditsForFailure($imagery, 'ClipJob');

            throw $exception;
        } finally {
            if (File::isDirectory($tilesDir)) {
                File::deleteDirectory($tilesDir);
            }
            if (File::exists($mergedPath)) {
                File::delete($mergedPath);
            }
        }
    }
}
