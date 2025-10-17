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

        $creditCost = (float) ($this->payload['credit_cost'] ?? 0);
        $refundedCredits = false;
        $outputPath = null;

        $handleFailure = function (string $message, array $context = []) use (
            $imagery,
            $creditService,
            $creditCost,
            &$refundedCredits,
            &$outputPath
        ) {
            Log::error($message, array_merge([
                'imagery_id' => $imagery->id,
            ], $context));

            if ($creditCost > 0 && !$refundedCredits) {
                try {
                    $creditService->addCreditsToUser($imagery->user_id, $creditCost, 'ClipJob');
                    $refundedCredits = true;
                } catch (Throwable $exception) {
                    Log::error('ProcessSentinelClipJob: Failed to refund credits.', [
                        'imagery_id' => $imagery->id,
                        'error' => $exception->getMessage(),
                    ]);
                }
            }

            $imagery->update([
                'processing_status' => 'error',
                'upload_status' => 'failed',
            ]);

            if ($outputPath && File::exists($outputPath)) {
                File::delete($outputPath);
            }
        };

        Log::info('ProcessSentinelClipJob started.', [
            'imagery_id' => $this->imageryId,
        ]);

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

        $scriptsBase = base_path('scripts');
        $scriptPath = $scriptsBase . DIRECTORY_SEPARATOR . 'process_clipped_multispectral_auto.py';
        $pythonPath = $pythonService->resolvePythonPath($scriptsBase);

        if (!$pythonPath) {
            $handleFailure('ProcessSentinelClipJob: Python executable not found for clipping process.');
            return;
        }

        if (!File::exists($scriptPath)) {
            $handleFailure('ProcessSentinelClipJob: Clipping script not found.', [
                'path' => $scriptPath,
            ]);
            return;
        }

        $geometry = $this->payload['geometry'] ?? null;
        if (!$geometry) {
            $handleFailure('ProcessSentinelClipJob: Geometry payload missing.');
            return;
        }

        $geometryJson = json_encode($geometry);
        if ($geometryJson === false) {
            $handleFailure('ProcessSentinelClipJob: Failed to encode geometry payload.', [
                'json_error' => json_last_error_msg(),
            ]);
            return;
        }

        if (File::exists($outputPath)) {
            File::delete($outputPath);
        }

        $overrides = array_filter([
            'CLIP_OUTPUT_PATH' => $outputPath,
            'CLIP_TILES_DIR' => $tilesDir,
            'CLIP_GEOMETRY' => $geometryJson,
            'CLIP_START_DATE' => $this->payload['start_date'] ?? null,
            'CLIP_LIMIT' => isset($this->payload['limit']) ? (string) $this->payload['limit'] : null,
            'CLIP_RESOLUTION' => isset($this->payload['resolution']) ? (string) $this->payload['resolution'] : null,
        ], static fn ($value) => !is_null($value) && $value !== '');

        $processEnv = $pythonService->buildProcessEnvironment($overrides);

        try {
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
                throw new \RuntimeException('Sentinel clip output was not generated.');
            }

            $sizeKb = File::size($outputPath) / 1024 ?: 0;

            $imagery->update([
                'upload_status' => 'done',
                'format' => pathinfo($outputFilename, PATHINFO_EXTENSION) ?: 'tif',
                'size' => $sizeKb,
                'path' => $relativeOutput,
            ]);

            Log::info('ProcessSentinelClipJob completed successfully.', [
                'imagery_id' => $this->imageryId,
                'output_path' => $outputPath,
            ]);

            ProcessImageryJob::dispatch($imagery->id)->onQueue('processing');
        } catch (Throwable $exception) {
            $handleFailure('ProcessSentinelClipJob failed: ' . $exception->getMessage(), [
                'exception' => get_class($exception),
            ]);
        } finally {
            if (File::isDirectory($tilesDir)) {
                File::deleteDirectory($tilesDir);
            }
        }
    }
}
