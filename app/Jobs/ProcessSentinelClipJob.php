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
        if (!$imagery) {
            Log::error('ProcessSentinelClipJob: Imagery record not found.', [
                'imagery_id' => $this->imageryId,
            ]);
            return;
        }

        $pythonService = new PythonService();
        $creditService = new CreditService();

        $creditCost = (float) ($this->payload['credit_cost'] ?? 0);

        $publicStorage = storage_path('app/public/imagery');
        $outputRelative = $this->payload['output_filename'] ?? $imagery->stored_name;
        $outputRelative = is_string($outputRelative) && $outputRelative !== '' ? $outputRelative : $imagery->stored_name;
        $outputPath = $publicStorage . DIRECTORY_SEPARATOR . ltrim($outputRelative, DIRECTORY_SEPARATOR);
        $relativeOutput = 'storage/imagery/' . ltrim($outputRelative, '/');

        $outputDirectory = dirname($outputPath);
        if (!File::isDirectory($outputDirectory)) {
            File::makeDirectory($outputDirectory, 0755, true, true);
        }

        if (File::exists($outputPath)) {
            File::delete($outputPath);
        }

        $tilesDir = storage_path('app/tmp/sentinel_clip_' . $this->imageryId);
        if (!File::isDirectory($tilesDir)) {
            File::makeDirectory($tilesDir, 0755, true, true);
        }
        $mergedPath = $tilesDir . DIRECTORY_SEPARATOR . 'merged_fixed.tif';

        $scriptsBase = base_path('scripts');
        $scriptPath = $scriptsBase . DIRECTORY_SEPARATOR . 'process_clipped_multispectral_auto.py';
        $pythonPath = $pythonService->resolvePythonPath($scriptsBase) ?? env('PYTHON_BINARY', 'python3');

        $geometry = $this->payload['geometry'] ?? null;

        if (!File::exists($scriptPath)) {
            Log::error('ProcessSentinelClipJob: Clipping script not found.', [
                'path' => $scriptPath,
            ]);
            $this->handleFailure($imagery, $creditService, $creditCost, $outputPath);
            return;
        }

        if (!$geometry || !is_array($geometry)) {
            Log::error('ProcessSentinelClipJob: Geometry payload missing or invalid.', [
                'imagery_id' => $this->imageryId,
            ]);
            $this->handleFailure($imagery, $creditService, $creditCost, $outputPath);
            return;
        }

        $dateTo = isset($this->payload['date_to']) ? Carbon::parse($this->payload['date_to']) : now();
        $dateFrom = isset($this->payload['date_from']) ? Carbon::parse($this->payload['date_from']) : $dateTo->copy()->subDays(30);
        if ($dateFrom->greaterThan($dateTo)) {
            [$dateFrom, $dateTo] = [$dateTo->copy()->subDays(30), $dateTo];
        }

        $overrides = [
            'COPERNICUS_CLIENT_ID' => (string) env('COPERNICUS_CLIENT_ID', ''),
            'COPERNICUS_CLIENT_SECRET' => (string) env('COPERNICUS_CLIENT_SECRET', ''),
            'SENTINEL_CLIP_GEOJSON' => json_encode($geometry, JSON_THROW_ON_ERROR),
            'SENTINEL_CLIP_TILE_DIR' => $tilesDir,
            'SENTINEL_CLIP_MERGED_PATH' => $mergedPath,
            'SENTINEL_CLIP_OUTPUT' => $outputPath,
            'SENTINEL_CLIP_DATE_FROM' => $dateFrom->toDateString(),
            'SENTINEL_CLIP_DATE_TO' => $dateTo->toDateString(),
        ];

        if (isset($this->payload['scene_id'])) {
            $overrides['SENTINEL_CLIP_SCENE_ID'] = (string) $this->payload['scene_id'];
        }

        if (isset($this->payload['limit'])) {
            $overrides['SENTINEL_CLIP_LIMIT'] = (string) $this->payload['limit'];
        }

        if (isset($this->payload['resolution'])) {
            $overrides['SENTINEL_CLIP_RESOLUTION'] = (string) $this->payload['resolution'];
        }

        if (isset($this->payload['nodata'])) {
            $overrides['SENTINEL_CLIP_NODATA'] = (string) $this->payload['nodata'];
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
                Log::error('[Sentinel Clip STDERR] ' . $stderr);
            }

            if (!$process->isSuccessful()) {
                throw new \RuntimeException('Sentinel clip processing failed.');
            }

            if (!File::exists($outputPath)) {
                throw new \RuntimeException('Sentinel clip output not created.');
            }

            $size = File::size($outputPath) ?: 0;

            $imagery->update([
                'upload_status' => 'done',
                'processing_status' => 'completed',
                'size' => $size,
                'path' => $relativeOutput,
                'processed_path' => $relativeOutput,
                'processed_at' => now(),
            ]);
        } catch (Throwable $exception) {
            Log::error('ProcessSentinelClipJob failed: ' . $exception->getMessage(), [
                'imagery_id' => $this->imageryId,
                'trace' => $exception->getTraceAsString(),
            ]);

            $this->handleFailure($imagery, $creditService, $creditCost, $outputPath);
        } finally {
            try {
                if (File::isDirectory($tilesDir)) {
                    File::deleteDirectory($tilesDir);
                }
            } catch (Throwable $cleanupException) {
                Log::warning('ProcessSentinelClipJob: Failed to cleanup temporary directory.', [
                    'imagery_id' => $this->imageryId,
                    'error' => $cleanupException->getMessage(),
                ]);
            }
        }
    }

    protected function handleFailure(ImageryData $imagery, CreditService $creditService, float $creditCost, string $outputPath): void
    {
        if (File::exists($outputPath)) {
            File::delete($outputPath);
        }

        if ($creditCost > 0 && $imagery->user_id) {
            $creditService->addCreditsToUser($imagery->user_id, $creditCost, 'ClipJob');
        }

        $imagery->update([
            'processing_status' => 'error',
            'upload_status' => 'failed',
        ]);
    }
}
