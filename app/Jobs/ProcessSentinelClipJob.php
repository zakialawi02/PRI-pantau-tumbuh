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
        $chargedCredits = (float) ($this->payload['charged_credits'] ?? 0);

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

        $geometryPayload = $this->payload['geometry'] ?? null;
        if (!$geometryPayload) {
            Log::error('ProcessSentinelClipJob: Geometry payload missing.', [
                'imagery_id' => $this->imageryId,
            ]);
            $imagery->update([
                'processing_status' => 'error',
                'upload_status' => 'failed',
            ]);
            if ($chargedCredits > 0) {
                $creditService->addCreditsToUser($imagery->user_id, $chargedCredits, 'ClipJobFailure');
            }
            return;
        }

        $geometry = $geometryPayload;
        if (is_array($geometryPayload) && isset($geometryPayload['type']) && strtolower($geometryPayload['type']) === 'feature' && isset($geometryPayload['geometry'])) {
            $geometry = $geometryPayload['geometry'];
        }

        $geometryJson = json_encode($geometry, JSON_UNESCAPED_SLASHES);
        if ($geometryJson === false) {
            Log::error('ProcessSentinelClipJob: Unable to encode geometry as JSON.', [
                'imagery_id' => $this->imageryId,
            ]);
            $imagery->update([
                'processing_status' => 'error',
                'upload_status' => 'failed',
            ]);
            if ($chargedCredits > 0) {
                $creditService->addCreditsToUser($imagery->user_id, $chargedCredits, 'ClipJobFailure');
            }
            return;
        }

        $startDate = $this->payload['start_date'] ?? now()->subMonth()->toDateString();
        $maxRecords = (int) ($this->payload['max_records'] ?? 10);
        if ($maxRecords <= 0) {
            $maxRecords = 10;
        }
        $resolution = (int) ($this->payload['resolution'] ?? 10);
        if ($resolution <= 0) {
            $resolution = 10;
        }

        File::cleanDirectory($tilesDir);

        $imagery->update([
            'processing_status' => 'processing',
            'upload_status' => 'uploading',
        ]);

        $overrides = [
            'CLIP_GEOMETRY' => $geometryJson,
            'CLIP_OUTPUT' => $outputPath,
            'CLIP_TILES_DIR' => $tilesDir,
            'CLIP_START_DATE' => $startDate,
            'CLIP_MAX_RECORDS' => (string) $maxRecords,
            'CLIP_RESOLUTION' => (string) $resolution,
        ];

        $copernicusId = env('COPERNICUS_CLIENT_ID');
        $copernicusSecret = env('COPERNICUS_CLIENT_SECRET');
        if (!empty($copernicusId)) {
            $overrides['COPERNICUS_CLIENT_ID'] = $copernicusId;
        }
        if (!empty($copernicusSecret)) {
            $overrides['COPERNICUS_CLIENT_SECRET'] = $copernicusSecret;
        }

        $processEnv = $pythonService->buildProcessEnvironment($overrides);

        $process = new Process([$pythonPath, $scriptPath], $scriptsBase, $processEnv);
        $process->setTimeout(7200);

        try {
            $process->run();

            $stdout = trim($process->getOutput());
            if ($stdout !== '') {
                Log::info('[ProcessSentinelClipJob:STDOUT] ' . $stdout);
            }

            $stderr = trim($process->getErrorOutput());
            if ($stderr !== '') {
                Log::warning('[ProcessSentinelClipJob:STDERR] ' . $stderr);
            }

            if (!$process->isSuccessful()) {
                throw new \RuntimeException('Sentinel clipping script failed to complete successfully.');
            }

            if (!File::exists($outputPath)) {
                throw new \RuntimeException('Expected clipped imagery output was not generated.');
            }

            $size = File::size($outputPath) ?: 0;

            $imagery->update([
                'upload_status' => 'done',
                'processing_status' => 'waiting',
                'format' => pathinfo($outputFilename, PATHINFO_EXTENSION) ?: 'tif',
                'path' => $relativeOutput,
                'size' => $size,
            ]);

            Log::info('ProcessSentinelClipJob completed successfully.', [
                'imagery_id' => $this->imageryId,
                'output_path' => $relativeOutput,
            ]);

            ProcessImageryJob::dispatch($imagery->id)->onQueue('processing');
        } catch (Throwable $exception) {
            Log::error('ProcessSentinelClipJob failed.', [
                'imagery_id' => $this->imageryId,
                'error' => $exception->getMessage(),
            ]);

            $imagery->update([
                'processing_status' => 'error',
                'upload_status' => 'failed',
            ]);

            if ($chargedCredits > 0) {
                $creditService->addCreditsToUser($imagery->user_id, $chargedCredits, 'ClipJobFailure');
            }
        } finally {
            if (File::exists($mergedPath)) {
                File::delete($mergedPath);
            }
            if (File::isDirectory($tilesDir)) {
                File::deleteDirectory($tilesDir);
            }
        }
    }
}
