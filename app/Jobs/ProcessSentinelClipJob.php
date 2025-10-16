<?php

namespace App\Jobs;

use Throwable;
use App\Models\ImageryData;
use Illuminate\Bus\Queueable;
use App\Services\CreditService;
use App\Services\PythonService;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\File;
use Illuminate\Queue\SerializesModels;
use Symfony\Component\Process\Process;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;

class ProcessSentinelClipJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public string $imageryId;
    public array $geometry;
    public string $sceneId;
    public ?string $sceneDatetime;
    public string $productLevel;
    public float $cloudLimit;
    public float $resolution;
    public array $metadata;

    public function __construct(
        string $imageryId,
        array $geometry,
        string $sceneId,
        ?string $sceneDatetime,
        string $productLevel,
        float $cloudLimit,
        float $resolution,
        array $metadata = []
    ) {
        $this->imageryId = $imageryId;
        $this->geometry = $geometry;
        $this->sceneId = $sceneId;
        $this->sceneDatetime = $sceneDatetime;
        $this->productLevel = $productLevel;
        $this->cloudLimit = $cloudLimit;
        $this->resolution = $resolution;
        $this->metadata = $metadata;
    }

    public function handle(): void
    {
        $creditService = new CreditService();
        $pythonService = new PythonService();
        $imagery = ImageryData::find($this->imageryId);

        if (!$imagery) {
            Log::error('ProcessSentinelClipJob: Imagery record not found.', [
                'imagery_id' => $this->imageryId,
            ]);
            return;
        }

        $baseStorage = storage_path('app/public');
        $imageryDir = $baseStorage . DIRECTORY_SEPARATOR . 'imagery';
        $workingDir = storage_path('app/tmp/sentinel-clip/' . $this->imageryId);
        $tilesDir = $workingDir . DIRECTORY_SEPARATOR . 'tiles';
        $mergedPath = $workingDir . DIRECTORY_SEPARATOR . 'merged_bbox.tif';
        $outputPath = $imageryDir . DIRECTORY_SEPARATOR . $imagery->stored_name;

        File::ensureDirectoryExists($imageryDir);
        File::ensureDirectoryExists($workingDir);
        File::ensureDirectoryExists($tilesDir);

        $scriptsBase = base_path('scripts');
        $scriptPath = $scriptsBase . DIRECTORY_SEPARATOR . 'process_clipped_multispectral_auto.py';
        $pythonPath = $pythonService->resolvePythonPath($scriptsBase);

        if (!$pythonPath || !File::exists($pythonPath)) {
            Log::error('ProcessSentinelClipJob: Python executable not found for Sentinel clipping.');
            $creditService->refundCreditsForFailure($imagery, 'ClipJob');
            $imagery->update(['processing_status' => 'error']);
            return;
        }

        if (!File::exists($scriptPath)) {
            Log::error('ProcessSentinelClipJob: Clipping script not found.', [
                'script' => $scriptPath,
            ]);
            $creditService->refundCreditsForFailure($imagery, 'ClipJob');
            $imagery->update(['processing_status' => 'error']);
            return;
        }

        if (File::exists($outputPath)) {
            File::delete($outputPath);
        }

        $overrides = [
            'S2_GEOJSON' => json_encode([
                'type' => 'Feature',
                'geometry' => $this->geometry,
            ]),
            'S2_SCENE_ID' => $this->sceneId,
            'S2_SCENE_DATETIME' => $this->sceneDatetime ?? '',
            'S2_PRODUCT_LEVEL' => $this->productLevel,
            'S2_MAX_CLOUD' => (string) $this->cloudLimit,
            'S2_RESOLUTION' => (string) $this->resolution,
            'S2_TILES_DIR' => $tilesDir,
            'S2_MERGED_PATH' => $mergedPath,
            'S2_OUTPUT_PATH' => $outputPath,
            'S2_TILE_MAX_PX' => (string) env('SENTINEL_TILE_MAX_PX', 2500),
            'S2_TIME_BUFFER_HOURS' => (string) env('SENTINEL_TIME_BUFFER_HOURS', 1),
        ];

        $processEnv = $pythonService->buildProcessEnvironment($overrides);

        $process = new Process([$pythonPath, $scriptPath], $scriptsBase, $processEnv);
        $process->setTimeout(7200);

        try {
            $process->run();

            $stdout = trim($process->getOutput());
            if ($stdout !== '') {
                Log::info('[Sentinel Clip STDOUT] ' . $stdout);
            }

            $stderr = trim($process->getErrorOutput());
            if ($stderr !== '') {
                Log::error('[Sentinel Clip STDERR] ' . $stderr);
            }

            if (!$process->isSuccessful()) {
                throw new \RuntimeException('Sentinel clipping script returned a non-zero exit code.');
            }

            if (!File::exists($outputPath)) {
                throw new \RuntimeException('Expected output file was not created.');
            }

            $fileSize = File::size($outputPath) ?: 0;

            $imagery->update([
                'upload_status' => 'done',
                'processing_status' => 'waiting',
                'size' => $fileSize,
                'format' => pathinfo($outputPath, PATHINFO_EXTENSION) ?: 'tif',
                'path' => 'storage/imagery/' . $imagery->stored_name,
            ]);

            Log::info('ProcessSentinelClipJob completed successfully.', [
                'imagery_id' => $this->imageryId,
                'output' => $outputPath,
                'metadata' => $this->metadata,
            ]);

            ProcessImageryJob::dispatch($imagery->id)->onQueue('processing');
        } catch (Throwable $exception) {
            Log::error('ProcessSentinelClipJob failed: ' . $exception->getMessage(), [
                'imagery_id' => $this->imageryId,
                'metadata' => $this->metadata,
            ]);

            if (File::exists($outputPath)) {
                File::delete($outputPath);
            }

            $creditService->refundCreditsForFailure($imagery, 'ClipJob');
            $imagery->update([
                'upload_status' => 'failed',
                'processing_status' => 'error',
            ]);
        } finally {
            try {
                if (File::isDirectory($workingDir)) {
                    File::deleteDirectory($workingDir);
                }
            } catch (Throwable $cleanupException) {
                Log::warning('ProcessSentinelClipJob: Unable to cleanup working directory.', [
                    'directory' => $workingDir,
                    'error' => $cleanupException->getMessage(),
                ]);
            }
        }
    }
}
