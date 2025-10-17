<?php

namespace App\Jobs;

use Throwable;
use JsonException;
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
            $this->markAsFailed($imagery, $creditService);
            return;
        }

        if (!File::exists($scriptPath)) {
            Log::error('ProcessSentinelClipJob: Clipping script not found.', [
                'path' => $scriptPath,
            ]);
            $this->markAsFailed($imagery, $creditService);
            return;
        }

        $geometry = $this->payload['geometry'] ?? null;
        if (!$geometry) {
            Log::error('ProcessSentinelClipJob: Geometry payload missing.', [
                'imagery_id' => $this->imageryId,
            ]);
            $this->markAsFailed($imagery, $creditService);
            return;
        }

        $imagery->update([
            'processing_status' => 'processing',
        ]);

        try {
            $geojson = json_encode($geometry, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            Log::error('ProcessSentinelClipJob: Failed to encode geometry payload.', [
                'imagery_id' => $this->imageryId,
                'error' => $exception->getMessage(),
            ]);
            $this->markAsFailed($imagery, $creditService);
            return;
        }

        $overrides = [
            'SENTINEL_CLIP_TILE_DIR' => $tilesDir,
            'SENTINEL_CLIP_MERGED_PATH' => $mergedPath,
            'SENTINEL_CLIP_OUTPUT' => $outputPath,
            'SENTINEL_CLIP_GEOJSON' => $geojson,
        ];

        $mapping = [
            'date_from' => 'SENTINEL_CLIP_DATE_FROM',
            'date_to' => 'SENTINEL_CLIP_DATE_TO',
            'limit' => 'SENTINEL_CLIP_LIMIT',
            'resolution' => 'SENTINEL_CLIP_RESOLUTION',
            'scene_id' => 'SENTINEL_CLIP_SCENE_ID',
        ];

        foreach ($mapping as $key => $envKey) {
            if (array_key_exists($key, $this->payload) && $this->payload[$key] !== null) {
                $overrides[$envKey] = (string) $this->payload[$key];
            }
        }

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

        if (!$process->isSuccessful()) {
            $this->markAsFailed($imagery, $creditService);
            $this->cleanupTemporaryDirectory($tilesDir);
            return;
        }

        if (!File::exists($outputPath)) {
            Log::error('ProcessSentinelClipJob: Expected output not found after processing.', [
                'imagery_id' => $this->imageryId,
                'output' => $outputPath,
            ]);
            $this->markAsFailed($imagery, $creditService);
            $this->cleanupTemporaryDirectory($tilesDir);
            return;
        }

        $fileSize = File::size($outputPath) ?: 0;

        $imagery->update([
            'upload_status' => 'done',
            'processing_status' => 'completed',
            'path' => $relativeOutput,
            'processed_path' => $relativeOutput,
            'processed_at' => now(),
            'format' => pathinfo($outputFilename, PATHINFO_EXTENSION) ?: 'tif',
            'size' => $fileSize,
        ]);

        $this->cleanupTemporaryDirectory($tilesDir);
    }

    private function markAsFailed(ImageryData $imagery, CreditService $creditService): void
    {
        $refunded = false;
        $charge = $this->payload['credit_charge'] ?? null;
        if (is_numeric($charge) && (float) $charge > 0) {
            $refunded = $creditService->addCreditsToUser($imagery->user_id, (float) $charge, 'ClipJob');
        }

        if (!$refunded) {
            $creditService->refundCreditsForFailure($imagery, 'ClipJob');
        }

        $imagery->update([
            'processing_status' => 'error',
            'upload_status' => 'failed',
        ]);
    }

    private function cleanupTemporaryDirectory(string $path): void
    {
        try {
            if (File::isDirectory($path)) {
                File::deleteDirectory($path);
            }
        } catch (Throwable $exception) {
            Log::warning('ProcessSentinelClipJob: Failed to clean temporary directory.', [
                'path' => $path,
                'error' => $exception->getMessage(),
            ]);
        }
    }
}
