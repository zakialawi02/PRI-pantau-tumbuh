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

        $geojson = $this->payload['geojson'] ?? null;
        if (!$geojson) {
            Log::error('ProcessSentinelClipJob: GeoJSON payload missing.', [
                'imagery_id' => $this->imageryId,
            ]);
            $this->handleFailure($imagery, $creditService, 'GeoJSON payload missing.');
            return;
        }

        $scriptsBase = base_path('scripts');
        $scriptPath = $scriptsBase . DIRECTORY_SEPARATOR . 'process_clipped_multispectral_auto.py';
        $pythonPath = $pythonService->resolvePythonPath($scriptsBase);

        if (!$pythonPath || !File::exists($pythonPath)) {
            Log::error('ProcessSentinelClipJob: Python executable not found for clipping process.');
            $this->handleFailure($imagery, $creditService, 'Python executable not found for clipping process.');
            return;
        }

        if (!File::exists($scriptPath)) {
            Log::error('ProcessSentinelClipJob: Clipping script not found.', [
                'path' => $scriptPath,
            ]);
            $this->handleFailure($imagery, $creditService, 'Clipping script not found.');
            return;
        }

        $publicStorage = storage_path('app/public');
        $imageryDir = $publicStorage . DIRECTORY_SEPARATOR . 'imagery';
        if (!File::isDirectory($imageryDir)) {
            File::makeDirectory($imageryDir, 0755, true, true);
        }

        $outputFilename = $this->payload['output_filename'] ?? $imagery->stored_name;
        $outputPath = $imageryDir . DIRECTORY_SEPARATOR . $outputFilename;
        if (File::exists($outputPath)) {
            File::delete($outputPath);
        }

        $tilesDir = storage_path('app/tmp/sentinel_clip_' . $this->imageryId);
        if (!File::isDirectory($tilesDir)) {
            File::makeDirectory($tilesDir, 0755, true, true);
        }
        $mergedPath = $tilesDir . DIRECTORY_SEPARATOR . 'merged_fixed.tif';

        $encodedGeojson = json_encode($geojson, JSON_UNESCAPED_SLASHES);
        if ($encodedGeojson === false) {
            Log::error('ProcessSentinelClipJob: Unable to encode GeoJSON payload.', [
                'imagery_id' => $this->imageryId,
            ]);
            $this->handleFailure($imagery, $creditService, 'Unable to encode GeoJSON payload.');
            return;
        }

        $overrides = [
            'SENTINEL_CLIP_TILE_DIR' => $tilesDir,
            'SENTINEL_CLIP_MERGED_PATH' => $mergedPath,
            'SENTINEL_CLIP_OUTPUT' => $outputPath,
            'SENTINEL_CLIP_GEOJSON' => $encodedGeojson,
        ];

        if (!empty($this->payload['date_from'])) {
            $overrides['SENTINEL_CLIP_DATE_FROM'] = (string) $this->payload['date_from'];
        }

        if (!empty($this->payload['date_to'])) {
            $overrides['SENTINEL_CLIP_DATE_TO'] = (string) $this->payload['date_to'];
        }

        if (!empty($this->payload['scene_id'])) {
            $overrides['SENTINEL_CLIP_SCENE_ID'] = (string) $this->payload['scene_id'];
        }

        if (!empty($this->payload['resolution'])) {
            $overrides['SENTINEL_CLIP_RESOLUTION'] = (string) $this->payload['resolution'];
        }

        if (!empty($this->payload['limit'])) {
            $overrides['SENTINEL_CLIP_LIMIT'] = (string) $this->payload['limit'];
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
                throw new \RuntimeException('Sentinel clipped imagery processing failed.');
            }

            if (!File::exists($outputPath)) {
                throw new \RuntimeException('Clipped imagery output was not created.');
            }

            $fileSize = File::size($outputPath) ?: 0;

            $imagery->update([
                'size' => $fileSize,
                'format' => pathinfo($outputFilename, PATHINFO_EXTENSION) ?: 'tif',
                'path' => 'storage/imagery/' . $outputFilename,
                'upload_status' => 'done',
                'processing_status' => 'waiting',
            ]);

            ProcessImageryJob::dispatch($imagery->id)->onQueue('processing');

            if (File::isDirectory($tilesDir)) {
                File::deleteDirectory($tilesDir);
            }
            if (File::exists($mergedPath)) {
                File::delete($mergedPath);
            }
        } catch (Throwable $exception) {
            Log::error('ProcessSentinelClipJob failed: ' . $exception->getMessage(), [
                'imagery_id' => $this->imageryId,
                'field_area_id' => $this->fieldAreaId,
            ]);

            if (File::exists($outputPath)) {
                File::delete($outputPath);
            }

            if (File::isDirectory($tilesDir)) {
                File::deleteDirectory($tilesDir);
            }

            $this->handleFailure($imagery, $creditService, $exception->getMessage());
        }
    }

    protected function handleFailure(ImageryData $imagery, CreditService $creditService, string $reason): void
    {
        $creditCost = (float) ($this->payload['credit_cost'] ?? 0);

        if ($creditCost > 0) {
            $creditService->addCreditsToUser((string) $imagery->user_id, $creditCost, 'ClipJob');
        }

        $imagery->update([
            'processing_status' => 'error',
            'upload_status' => 'failed',
        ]);

        Log::error('ProcessSentinelClipJob handled failure.', [
            'imagery_id' => $imagery->id,
            'reason' => $reason,
        ]);
    }
}
