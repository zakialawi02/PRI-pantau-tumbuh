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
    public ?string $fieldAreaId;
    public array $payload;

    public function __construct(string $imageryId, ?string $fieldAreaId = null, array $payload = [])
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
            $this->refundCredits($creditService, $imagery, $this->payload['deducted_credits'] ?? null);
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
            $this->refundCredits($creditService, $imagery, $this->payload['deducted_credits'] ?? null);
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
            $this->refundCredits($creditService, $imagery, $this->payload['deducted_credits'] ?? null);
            $imagery->update([
                'processing_status' => 'error',
                'upload_status' => 'failed',
            ]);
            return;
        }

        try {
            $geometryJson = json_encode($geometry, JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            Log::error('ProcessSentinelClipJob: Failed to encode geometry payload.', [
                'imagery_id' => $this->imageryId,
                'error' => $exception->getMessage(),
            ]);
            $this->refundCredits($creditService, $imagery, $this->payload['deducted_credits'] ?? null);
            $imagery->update([
                'processing_status' => 'error',
                'upload_status' => 'failed',
            ]);
            return;
        }

        $startDate = $this->payload['start_date'] ?? now()->toDateString();
        $maxRecords = (int) ($this->payload['max_records'] ?? 10);
        $resolution = (int) ($this->payload['resolution'] ?? 10);
        $nodataValue = $this->payload['nodata_value'] ?? 0;
        $dataCollection = $this->payload['data_collection'] ?? 'SENTINEL2_L2A';

        $overrides = [
            'S2_GEOJSON' => $geometryJson,
            'S2_START_DATE' => $startDate,
            'S2_MAX_RECORDS' => (string) $maxRecords,
            'S2_OUTPUT_PATH' => $outputPath,
            'S2_TILES_DIR' => $tilesDir,
            'S2_MERGED_PATH' => $mergedPath,
            'S2_RESOLUTION' => (string) $resolution,
            'S2_NODATA_VALUE' => (string) $nodataValue,
            'S2_DATA_COLLECTION' => $dataCollection,
        ];

        $processEnv = $pythonService->buildProcessEnvironment($overrides);

        $process = new Process([$pythonPath, $scriptPath], $scriptsBase, $processEnv);
        $process->setTimeout(7200);

        try {
            if (File::exists($outputPath)) {
                File::delete($outputPath);
            }

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
                throw new \RuntimeException('Sentinel clip output not found after processing.');
            }

            $outputSize = File::size($outputPath) ?: 0;

            $imagery->update([
                'format' => pathinfo($outputFilename, PATHINFO_EXTENSION) ?: 'tif',
                'size' => $outputSize,
                'upload_status' => 'done',
                'path' => $relativeOutput,
            ]);

            ProcessImageryJob::dispatch($imagery->id)->onQueue('processing');
        } catch (Throwable $exception) {
            Log::error('ProcessSentinelClipJob failed: ' . $exception->getMessage(), [
                'imagery_id' => $this->imageryId,
                'trace' => $exception->getTraceAsString(),
            ]);

            $this->refundCredits($creditService, $imagery, $this->payload['deducted_credits'] ?? null);

            $imagery->update([
                'processing_status' => 'error',
                'upload_status' => 'failed',
            ]);
        } finally {
            if (File::isDirectory($tilesDir)) {
                File::deleteDirectory($tilesDir);
            }
            if (File::exists($mergedPath)) {
                File::delete($mergedPath);
            }
        }
    }

    private function refundCredits(CreditService $creditService, ImageryData $imagery, $amount = null): void
    {
        if ($amount !== null && is_numeric($amount) && $amount > 0) {
            $creditService->addCreditsToUser($imagery->user_id, (float) $amount, 'ClipJob');
            return;
        }

        $creditService->refundCreditsForFailure($imagery, 'ClipJob');
    }
}
