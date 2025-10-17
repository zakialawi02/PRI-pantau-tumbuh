<?php

namespace App\Jobs;

use Throwable;
use Carbon\Carbon;
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

use function json_encode;
use App\Jobs\ProcessImageryJob;

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

        $clippedOutput = $tilesDir . DIRECTORY_SEPARATOR . 'clipped_masked.tif';

        try {
            $imagery->update([
                'upload_status' => 'processing',
                'processing_status' => 'processing',
            ]);

            $dateFrom = $this->payload['date_from'] ?? null;
            $dateTo = $this->payload['date_to'] ?? null;

            $fromDate = $dateFrom ? Carbon::parse($dateFrom) : now()->subDays(30);
            $toDate = $dateTo ? Carbon::parse($dateTo) : now();

            if ($fromDate->greaterThan($toDate)) {
                [$fromDate, $toDate] = [$toDate, $fromDate];
            }

            $overrides = [
                'SENTINEL_CLIP_GEOJSON' => json_encode($geometry),
                'SENTINEL_CLIP_TILE_DIR' => $tilesDir,
                'SENTINEL_CLIP_MERGED_PATH' => $mergedPath,
                'SENTINEL_CLIP_OUTPUT' => $clippedOutput,
                'SENTINEL_CLIP_DATE_FROM' => $fromDate->toDateString(),
                'SENTINEL_CLIP_DATE_TO' => $toDate->toDateString(),
            ];

            if (!empty($this->payload['scene_id'])) {
                $overrides['SENTINEL_CLIP_SCENE_ID'] = (string) $this->payload['scene_id'];
            }

            if (!empty($this->payload['resolution'])) {
                $overrides['SENTINEL_CLIP_RESOLUTION'] = (string) $this->payload['resolution'];
            }

            if (!empty($this->payload['limit'])) {
                $overrides['SENTINEL_CLIP_LIMIT'] = (string) $this->payload['limit'];
            }

            if (!empty($this->payload['nodata'])) {
                $overrides['SENTINEL_CLIP_NODATA'] = (string) $this->payload['nodata'];
            }

            if (!empty($this->payload['max_cloud'])) {
                $overrides['SENTINEL_CLIP_MAX_CLOUD'] = (string) $this->payload['max_cloud'];
            }

            if (!empty($this->payload['collection'])) {
                $overrides['SENTINEL_CLIP_COLLECTION'] = (string) $this->payload['collection'];
            }

            $processEnv = $pythonService->buildProcessEnvironment($overrides);

            $process = new Process([$pythonPath, $scriptPath], $scriptsBase, $processEnv);
            $process->setTimeout(7200);
            $process->run();

            $stdout = trim($process->getOutput());
            if ($stdout !== '') {
                Log::info('[ProcessSentinelClipJob][STDOUT] ' . $stdout);
            }

            $stderr = trim($process->getErrorOutput());
            if ($stderr !== '') {
                Log::warning('[ProcessSentinelClipJob][STDERR] ' . $stderr);
            }

            if (!$process->isSuccessful()) {
                throw new \RuntimeException('Sentinel clip script failed to execute successfully.');
            }

            if (!File::exists($clippedOutput)) {
                throw new \RuntimeException('Sentinel clip output file was not produced.');
            }

            if (File::exists($outputPath)) {
                File::delete($outputPath);
            }

            File::move($clippedOutput, $outputPath);

            $outputSize = File::exists($outputPath) ? File::size($outputPath) : 0;

            $imagery->update([
                'upload_status' => 'done',
                'processing_status' => 'waiting',
                'size' => $outputSize,
                'path' => $relativeOutput,
            ]);

            Log::info('ProcessSentinelClipJob completed successfully.', [
                'imagery_id' => $this->imageryId,
                'output_path' => $outputPath,
            ]);

            ProcessImageryJob::dispatch($imagery->id)->onQueue('processing');
        } catch (Throwable $exception) {
            Log::error('ProcessSentinelClipJob failed.', [
                'imagery_id' => $this->imageryId,
                'error' => $exception->getMessage(),
            ]);

            if (!empty($this->payload['credit_cost'])) {
                $creditService->addCreditsToUser(
                    $imagery->user_id,
                    (float) $this->payload['credit_cost'],
                    'ClipJob'
                );
            } else {
                $creditService->refundCreditsForFailure($imagery, 'ClipJob');
            }

            $imagery->update([
                'processing_status' => 'error',
                'upload_status' => 'failed',
            ]);
        } finally {
            if (File::isDirectory($tilesDir)) {
                try {
                    File::deleteDirectory($tilesDir);
                } catch (Throwable $cleanupException) {
                    Log::warning('ProcessSentinelClipJob: Failed to clean temp directory.', [
                        'imagery_id' => $this->imageryId,
                        'error' => $cleanupException->getMessage(),
                    ]);
                }
            }
        }
    }
}
