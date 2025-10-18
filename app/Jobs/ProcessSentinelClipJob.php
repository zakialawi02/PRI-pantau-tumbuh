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

        $outputFilename = basename($this->payload['output_filename'] ?? $imagery->stored_name);
        $outputPath = $clipDir . DIRECTORY_SEPARATOR . $outputFilename;
        $relativeOutput = 'storage/imagery/clipped/' . $outputFilename;

        $tilesDir = storage_path('app/tmp/sentinel_clip_' . $this->imageryId);
        if (File::isDirectory($tilesDir)) {
            File::deleteDirectory($tilesDir);
        }
        File::makeDirectory($tilesDir, 0755, true, true);
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

        if (File::exists($outputPath)) {
            File::delete($outputPath);
        }
        if (File::exists($mergedPath)) {
            File::delete($mergedPath);
        }

        try {
            $geojson = $this->payload['geojson'] ?? [
                'type' => 'FeatureCollection',
                'features' => [[
                    'type' => 'Feature',
                    'properties' => [],
                    'geometry' => $geometry,
                ]],
            ];

            $overrides = [
                'SENTINELHUB_CLIENT_ID' => env('COPERNICUS_CLIENT_ID'),
                'SENTINELHUB_SECRET_ID' => env('COPERNICUS_CLIENT_SECRET'),
                'SENTINEL_CLIP_TILE_DIR' => $tilesDir,
                'SENTINEL_CLIP_MERGED_PATH' => $mergedPath,
                'SENTINEL_CLIP_OUTPUT' => $outputPath,
                'SENTINEL_CLIP_GEOJSON' => json_encode($geojson, JSON_THROW_ON_ERROR),
            ];

            $optionalEnvMap = [
                'SENTINELHUB_CLIENT_ID' => 'SENTINEL_CLIP_CLIENT_ID',
                'SENTINELHUB_SECRET_ID' => 'SENTINELHUB_SECRET_ID',
                'date_from' => 'SENTINEL_CLIP_DATE_FROM',
                'date_to' => 'SENTINEL_CLIP_DATE_TO',
                'limit' => 'SENTINEL_CLIP_LIMIT',
                'resolution' => 'SENTINEL_CLIP_RESOLUTION',
                'nodata' => 'SENTINEL_CLIP_NODATA',
                'scene_id' => 'SENTINEL_CLIP_SCENE_ID',
            ];

            foreach ($optionalEnvMap as $payloadKey => $envKey) {
                if (isset($this->payload[$payloadKey]) && $this->payload[$payloadKey] !== null && $this->payload[$payloadKey] !== '') {
                    $overrides[$envKey] = (string) $this->payload[$payloadKey];
                }
            }

            $imagery->update([
                'processing_status' => 'processing',
                'path' => $relativeOutput,
                'stored_name' => str_starts_with($imagery->stored_name, 'clipped/') ? $imagery->stored_name : 'clipped/' . $outputFilename,
            ]);

            $processEnv = $pythonService->buildProcessEnvironment($overrides);

            $process = new Process([$pythonPath, $scriptPath], $scriptsBase, $processEnv);
            $process->setTimeout(7200);
            $process->run();

            $stdout = trim($process->getOutput());
            if ($stdout !== '') {
                Log::info('[Sentinel Clip STDOUT] ' . $stdout);
            }

            $stderr = trim($process->getErrorOutput());
            if ($stderr !== '') {
                Log::error('[Sentinel Clip STDERR] ' . $stderr);
            }

            if (!$process->isSuccessful() || !File::exists($outputPath)) {
                throw new \RuntimeException('Sentinel clip processing failed or output file missing.');
            }

            $fileSize = File::size($outputPath) ?: 0;

            $imagery->update([
                'upload_status' => 'done',
                'processing_status' => 'completed',
                'format' => pathinfo($outputFilename, PATHINFO_EXTENSION) ?: 'tif',
                'size' => $fileSize,
                'path' => $relativeOutput,
            ]);

            Log::info('ProcessSentinelClipJob completed successfully.', [
                'imagery_id' => $this->imageryId,
                'field_area_id' => $this->fieldAreaId,
                'output' => $relativeOutput,
            ]);

            ProcessImageryJob::dispatch($imagery->id)->onQueue('processing');
        } catch (Throwable $exception) {
            Log::error('ProcessSentinelClipJob failed.', [
                'imagery_id' => $this->imageryId,
                'field_area_id' => $this->fieldAreaId,
                'error' => $exception->getMessage(),
            ]);

            if (File::exists($outputPath)) {
                File::delete($outputPath);
            }

            $creditService->refundCreditsForFailure($imagery, 'ClipJob');
            $imagery->update([
                'processing_status' => 'error',
                'upload_status' => 'failed',
            ]);
        } finally {
            if (File::isDirectory($tilesDir)) {
                File::deleteDirectory($tilesDir);
            }
        }
    }
}
