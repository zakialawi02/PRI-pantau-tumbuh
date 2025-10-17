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
        $creditService = new CreditService();
        $pythonService = new PythonService();

        if (!$imagery) {
            Log::error('ProcessSentinelClipJob: Imagery record not found.', [
                'imagery_id' => $this->imageryId,
            ]);

            return;
        }

        $payload = $this->payload ?? [];

        $publicStoragePath = storage_path('app/public');
        $imageryDirectory = $publicStoragePath . DIRECTORY_SEPARATOR . 'imagery';
        $tilesDirectory = storage_path('app/tmp/sentinel-clip/' . $imagery->id);
        $scriptsBase = base_path('scripts');
        $scriptPath = $scriptsBase . DIRECTORY_SEPARATOR . 'process_clipped_multispectral_auto.py';

        try {
            if (!File::isDirectory($imageryDirectory)) {
                File::makeDirectory($imageryDirectory, 0755, true);
            }

            File::deleteDirectory($tilesDirectory);
            File::makeDirectory($tilesDirectory, 0755, true);

            $outputFilename = $imagery->stored_name;
            $outputPath = $imageryDirectory . DIRECTORY_SEPARATOR . $outputFilename;

            if (File::exists($outputPath)) {
                File::delete($outputPath);
            }

            if (!File::exists($scriptPath)) {
                throw new \RuntimeException('Sentinel clip processing script not found.');
            }

            $pythonPath = $pythonService->resolvePythonPath($scriptsBase);

            if (!$pythonPath) {
                throw new \RuntimeException('Python executable for Sentinel clip processing not found.');
            }

            $geometry = $payload['geometry'] ?? [];
            $geometryJson = json_encode($geometry, JSON_UNESCAPED_SLASHES);

            if (empty($geometryJson)) {
                throw new \RuntimeException('Clip geometry is required for processing Sentinel imagery.');
            }

            $scene = $payload['scene'] ?? [];
            $sceneTime = $scene['acquired_at'] ?? null;
            $collection = $scene['collection'] ?? 'S2MSI2A';
            $productId = $scene['product_id'] ?? null;
            $sceneId = $scene['id'] ?? null;

            $envOverrides = [
                'S2_CLIP_GEOMETRY' => $geometryJson,
                'S2_CLIP_OUTPUT' => $outputPath,
                'S2_CLIP_TILE_DIR' => $tilesDirectory,
                'S2_CLIP_COLLECTION' => $collection,
                'S2_CLIP_PRODUCT_ID' => $productId,
                'S2_CLIP_SCENE_ID' => $sceneId,
                'S2_CLIP_SCENE_TIME' => $sceneTime,
                'S2_CLIP_RESOLUTION' => (string)($payload['resolution'] ?? 10),
                'S2_CLIP_LIMIT' => (string)($payload['limit'] ?? 8),
                'S2_CLIP_TIME_BUFFER_MINUTES' => (string)($payload['time_buffer_minutes'] ?? 90),
                'S2_CLIP_MAX_TILE_SIZE' => (string)($payload['max_tile_size'] ?? 2500),
                'PYTHONUNBUFFERED' => '1',
            ];

            $processEnv = $pythonService->buildProcessEnvironment($envOverrides);

            $process = new Process([$pythonPath, $scriptPath], $scriptsBase, $processEnv);
            $process->setTimeout(7200);

            $imagery->update([
                'processing_status' => 'processing',
            ]);

            $process->run();

            $stderr = trim($process->getErrorOutput());
            $stdout = trim($process->getOutput());

            if ($stdout !== '') {
                Log::info('[Sentinel Clip STDOUT] ' . $stdout);
            }

            if ($stderr !== '') {
                Log::warning('[Sentinel Clip STDERR] ' . $stderr);
            }

            if (!$process->isSuccessful()) {
                throw new \RuntimeException('Sentinel clip processing failed.');
            }

            if (!File::exists($outputPath)) {
                throw new \RuntimeException('Sentinel clip output file was not created.');
            }

            $fileSize = File::size($outputPath) ?: 0;

            $relativePath = 'storage/imagery/' . $outputFilename;

            $imagery->update([
                'upload_status' => 'done',
                'processing_status' => 'completed',
                'processed_path' => $relativePath,
                'processed_at' => Carbon::now(),
                'path' => $relativePath,
                'format' => pathinfo($outputFilename, PATHINFO_EXTENSION) ?: 'tif',
                'size' => $fileSize,
            ]);

            Log::info('ProcessSentinelClipJob completed successfully.', [
                'imagery_id' => $imagery->id,
                'field_area_id' => $this->fieldAreaId,
                'output' => $relativePath,
            ]);

            File::deleteDirectory($tilesDirectory);
        } catch (Throwable $exception) {
            Log::error('ProcessSentinelClipJob failed.', [
                'imagery_id' => $this->imageryId,
                'field_area_id' => $this->fieldAreaId,
                'message' => $exception->getMessage(),
            ]);

            $creditService->refundCreditsForFailure($imagery, 'SentinelClipJob');

            if ($imagery->exists) {
                $imagery->update([
                    'processing_status' => 'error',
                    'upload_status' => 'failed',
                ]);
            }

            File::deleteDirectory($tilesDirectory);

            throw $exception;
        }
    }
}
