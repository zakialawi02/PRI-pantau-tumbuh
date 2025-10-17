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
            $this->refundCredits($creditService, $imagery);
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
            $this->refundCredits($creditService, $imagery);
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
            $this->refundCredits($creditService, $imagery);
            $imagery->update([
                'processing_status' => 'error',
                'upload_status' => 'failed',
            ]);
            return;
        }

        if (($geometry['type'] ?? null) !== 'FeatureCollection') {
            $geometry = [
                'type' => 'FeatureCollection',
                'features' => [$geometry],
            ];
        }

        $dateFrom = $this->payload['date_from'] ?? now()->subDays(30)->toDateString();
        $dateTo = $this->payload['date_to'] ?? now()->toDateString();
        $limit = (int) ($this->payload['limit'] ?? 50);
        $resolution = (int) ($this->payload['resolution'] ?? 10);
        $nodata = (float) ($this->payload['nodata'] ?? 0);
        $sceneId = $this->payload['scene_id'] ?? null;

        $overrides = [
            'SENTINEL_CLIP_GEOJSON' => json_encode($geometry),
            'SENTINEL_CLIP_TILE_DIR' => $tilesDir,
            'SENTINEL_CLIP_MERGED_PATH' => $mergedPath,
            'SENTINEL_CLIP_OUTPUT' => $outputPath,
            'SENTINEL_CLIP_DATE_FROM' => $dateFrom,
            'SENTINEL_CLIP_DATE_TO' => $dateTo,
            'SENTINEL_CLIP_LIMIT' => (string) max($limit, 1),
            'SENTINEL_CLIP_RESOLUTION' => (string) max($resolution, 10),
            'SENTINEL_CLIP_NODATA' => (string) $nodata,
        ];

        if (!empty($sceneId)) {
            $overrides['SENTINEL_CLIP_SCENE_ID'] = $sceneId;
        }

        $processEnv = $pythonService->buildProcessEnvironment($overrides);

        try {
            $imagery->update([
                'processing_status' => 'processing',
            ]);

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
                throw new \RuntimeException('Sentinel clip output file was not created.');
            }

            $outputSize = File::size($outputPath) ?: 0;

            $imagery->update([
                'upload_status' => 'done',
                'processing_status' => 'completed',
                'processed_path' => $relativeOutput,
                'processed_at' => now(),
                'path' => $relativeOutput,
                'format' => pathinfo($outputFilename, PATHINFO_EXTENSION) ?: 'tif',
                'size' => $outputSize,
            ]);
        } catch (Throwable $exception) {
            Log::error('ProcessSentinelClipJob failed: ' . $exception->getMessage(), [
                'imagery_id' => $this->imageryId,
                'field_area_id' => $this->fieldAreaId,
                'trace' => $exception->getTraceAsString(),
            ]);

            if (File::exists($outputPath)) {
                File::delete($outputPath);
            }

            $this->refundCredits($creditService, $imagery);

            $imagery->update([
                'processing_status' => 'error',
                'upload_status' => 'failed',
            ]);

            $this->fail($exception);
        } finally {
            if (File::isDirectory($tilesDir)) {
                File::deleteDirectory($tilesDir);
            }

            if (File::exists($mergedPath)) {
                File::delete($mergedPath);
            }
        }
    }

    private function refundCredits(CreditService $creditService, ImageryData $imagery): void
    {
        $creditCost = (float) ($this->payload['credit_cost'] ?? 0);
        if ($creditCost <= 0) {
            return;
        }

        $creditService->addCreditsToUser($imagery->user_id, $creditCost, 'ClipJobFailure');
    }
}
