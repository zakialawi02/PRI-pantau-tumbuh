<?php

namespace App\Jobs;

use App\Models\ImageryData;
use App\Services\CreditService;
use App\Services\PythonService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Symfony\Component\Process\Process;
use Throwable;

class ProcessSentinelClipJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public string $imageryId;
    public array $options;

    public function __construct(string $imageryId, array $options)
    {
        $this->imageryId = $imageryId;
        $this->options = $options;
    }

    public function handle(): void
    {
        $pythonService = new PythonService();
        $creditService = new CreditService();

        $imagery = ImageryData::find($this->imageryId);

        if (!$imagery) {
            Log::error('ProcessSentinelClipJob: Imagery record not found.', [
                'imagery_id' => $this->imageryId,
            ]);
            return;
        }

        $scriptsBase = base_path('scripts');
        $scriptPath = $scriptsBase . DIRECTORY_SEPARATOR . 'process_clipped_multispectral_auto.py';
        $pythonPath = $pythonService->resolvePythonPath($scriptsBase);

        if (!$pythonPath || !File::exists($pythonPath)) {
            Log::error('ProcessSentinelClipJob: Python executable for clip processing not found.');
            $creditService->refundCreditsForFailure($imagery, 'ClipJob');
            $imagery->update(['processing_status' => 'error']);
            return;
        }

        if (!File::exists($scriptPath)) {
            Log::error('ProcessSentinelClipJob: Clip processing script missing.', [
                'path' => $scriptPath,
            ]);
            $creditService->refundCreditsForFailure($imagery, 'ClipJob');
            $imagery->update(['processing_status' => 'error']);
            return;
        }

        $publicStorage = storage_path('app/public');
        $imageryDirectory = $publicStorage . DIRECTORY_SEPARATOR . 'imagery';
        $clipDirectory = $imageryDirectory . DIRECTORY_SEPARATOR . 'clip';

        if (!File::isDirectory($imageryDirectory)) {
            File::makeDirectory($imageryDirectory, 0755, true);
        }

        if (!File::isDirectory($clipDirectory)) {
            File::makeDirectory($clipDirectory, 0755, true);
        }

        $outputFilename = $imagery->stored_name;
        $outputPath = $clipDirectory . DIRECTORY_SEPARATOR . $outputFilename;
        $tileDirectory = $clipDirectory . DIRECTORY_SEPARATOR . pathinfo($outputFilename, PATHINFO_FILENAME) . '_tiles_' . Str::lower(Str::random(6));

        if (!File::isDirectory($tileDirectory)) {
            File::makeDirectory($tileDirectory, 0755, true);
        }

        $overrides = [
            'S2_CLIP_GEOJSON' => $this->options['geometry'] ?? '',
            'S2_CLIP_DATE_FROM' => $this->options['date_from'] ?? '',
            'S2_CLIP_DATE_TO' => $this->options['date_to'] ?? '',
            'S2_CLIP_MAX_CLOUD' => isset($this->options['max_cloud']) ? (string) $this->options['max_cloud'] : null,
            'S2_CLIP_LIMIT' => isset($this->options['limit']) ? (string) $this->options['limit'] : null,
            'S2_CLIP_RESOLUTION' => isset($this->options['resolution']) ? (string) $this->options['resolution'] : null,
            'S2_CLIP_NODATA' => isset($this->options['nodata']) ? (string) $this->options['nodata'] : null,
            'S2_CLIP_OUTPUT' => $outputPath,
            'S2_CLIP_TILE_DIR' => $tileDirectory,
            'S2_CLIP_PRODUCT_LEVEL' => $this->options['product_level'] ?? 'S2MSI2A',
        ];

        if (!empty($this->options['scene_id'])) {
            $overrides['S2_CLIP_SCENE_ID'] = (string) $this->options['scene_id'];
        }

        if (!empty($this->options['scene_datetime'])) {
            $overrides['S2_CLIP_SCENE_DATETIME'] = (string) $this->options['scene_datetime'];
        }

        foreach ($overrides as $key => $value) {
            if ($value === null) {
                unset($overrides[$key]);
            }
        }

        try {
            $processEnv = $pythonService->buildProcessEnvironment($overrides);

            $process = new Process([$pythonPath, $scriptPath], $scriptsBase, $processEnv);
            $process->setTimeout(10800); // allow up to 3 hours for clipping
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
                throw new \RuntimeException('Clip processing failed.');
            }

            if (!File::exists($outputPath)) {
                throw new \RuntimeException('Expected clip output not found at ' . $outputPath);
            }

            $fileSize = File::size($outputPath) ?: 0;
            $relativePath = 'storage/imagery/clip/' . $outputFilename;

            $imagery->update([
                'upload_status' => 'done',
                'processing_status' => 'waiting',
                'size' => $fileSize,
                'format' => pathinfo($outputFilename, PATHINFO_EXTENSION) ?: 'tif',
                'path' => $relativePath,
            ]);

            Log::info('ProcessSentinelClipJob completed.', [
                'imagery_id' => $this->imageryId,
                'output_path' => $outputPath,
            ]);

            ProcessImageryJob::dispatch($imagery->id)->onQueue('processing');
        } catch (Throwable $exception) {
            Log::error('ProcessSentinelClipJob failed: ' . $exception->getMessage(), [
                'imagery_id' => $this->imageryId,
                'options' => $this->options,
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
            if (File::isDirectory($tileDirectory)) {
                File::deleteDirectory($tileDirectory);
            }
        }
    }
}
