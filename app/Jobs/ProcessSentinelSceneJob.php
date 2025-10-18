<?php

namespace App\Jobs;

use Throwable;
use App\Models\ImageryData;
use App\Services\CreditService;
use App\Services\GeoServerService;
use App\Services\PythonService;
use Illuminate\Bus\Queueable;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Queue\SerializesModels;
use Symfony\Component\Process\Process;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;

class ProcessSentinelSceneJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public string $imageryId;
    public string $downloadUrl;
    public string $zipDirectory;
    public string $zipFilename;
    public string $outputFilename;
    public string $displayName;
    public array $metadata;

    public function __construct(
        string $imageryId,
        string $downloadUrl,
        string $zipDirectory,
        string $zipFilename,
        string $outputFilename,
        string $displayName,
        array $metadata = []
    ) {
        $this->imageryId = $imageryId;
        $this->downloadUrl = $downloadUrl;
        $this->zipDirectory = trim($zipDirectory, '/');
        $this->zipFilename = $zipFilename;
        $this->outputFilename = $outputFilename;
        $this->displayName = $displayName;
        $this->metadata = $metadata;
    }

    public function handle(): void
    {
        $creditService = new CreditService();
        $pythonService = new PythonService();
        $imagery = ImageryData::find($this->imageryId);

        Log::info('ProcessSentinelSceneJob started.', [
            'imagery_id' => $this->imageryId,
            'metadata' => $this->metadata ?? "null",
        ]);

        if (!$imagery) {
            Log::error('ProcessSentinelSceneJob: Imagery record not found.', [
                'imagery_id' => $this->imageryId,
            ]);
            return;
        }

        $publicStorage = storage_path('app/public');
        $imageryDir = $publicStorage . DIRECTORY_SEPARATOR . 'imagery';
        $sourceDir = $this->zipDirectory !== ''
            ? $publicStorage . DIRECTORY_SEPARATOR . $this->zipDirectory
            : $imageryDir;

        if (!File::isDirectory($imageryDir)) {
            File::makeDirectory($imageryDir, 0755, true);
        }

        if (!File::isDirectory($sourceDir)) {
            File::makeDirectory($sourceDir, 0755, true);
        }

        $zipPath = $sourceDir . DIRECTORY_SEPARATOR . $this->zipFilename;
        $outputPath = $imageryDir . DIRECTORY_SEPARATOR . $this->outputFilename;

        try {
            if (File::exists($zipPath)) {
                File::delete($zipPath);
            }

            $response = Http::withOptions([
                'sink' => $zipPath,
                'timeout' => 0,
                'connect_timeout' => 300,
            ])->get($this->downloadUrl);

            if ($response->failed()) {
                Log::error('ProcessSentinelSceneJob: Sentinel scene download failed.', [
                    'imagery_id' => $this->imageryId,
                    'zip_path' => $zipPath,
                    'status' => $response->status(),
                ]);
                throw new \RuntimeException('Unable to download Sentinel scene. HTTP status: ' . $response->status());
            }

            if (!File::exists($zipPath)) {
                Log::error('ProcessSentinelSceneJob: Sentinel scene download failed.', [
                    'imagery_id' => $this->imageryId,
                    'zip_path' => $zipPath,
                    'status' => $response->status(),
                ]);
                throw new \RuntimeException('Sentinel scene download missing after request.');
            }

            $downloadedSize = File::size($zipPath) / 1024 ?: 0;

            Log::info('ProcessSentinelSceneJob: Sentinel scene downloaded.', [
                'imagery_id' => $this->imageryId,
                'zip_path' => $zipPath,
                'size' => $downloadedSize,
            ]);

            $imagery->update([
                'format' => "zip",
                'upload_status' => 'done',
                'size' => $downloadedSize,
            ]);

            if (File::exists($outputPath)) {
                File::delete($outputPath);
            }

            $scriptsBase = base_path('scripts');
            $scriptPath = $scriptsBase . DIRECTORY_SEPARATOR . 'process_to_multispectral_auto.py';
            $pythonPath = $pythonService->resolvePythonPath($scriptsBase);

            if (!$pythonPath) {
                Log::error('ProcessSentinelSceneJob: Python executable for Sentinel processing not found.');
                throw new \RuntimeException('Python executable for Sentinel processing not found.');
            }

            if (!File::exists($scriptPath)) {
                Log::error('ProcessSentinelSceneJob: Multispectral processing script not found.');
                throw new \RuntimeException('Multispectral processing script not found.');
            }

            $overrides = [
                'S2_SOURCE' => $zipPath,
                'S2_OUTPUT' => $outputPath,
                'S2_OVERWRITE' => '1',
            ];

            $resampling = env('SENTINEL_RESAMPLING_METHOD');
            if (!empty($resampling)) {
                $overrides['S2_RESAMPLING'] = (string) $resampling;
            }

            $processEnv = $pythonService->buildProcessEnvironment($overrides);

            $process = new Process([$pythonPath, $scriptPath], $scriptsBase, $processEnv);
            $process->setTimeout(7200);
            $process->run();

            $stdout = trim($process->getOutput());
            if ($stdout !== '') {
                Log::info('[Sentinel Multispectral STDOUT] ' . $stdout);
            }

            $stderr = trim($process->getErrorOutput());
            if ($stderr !== '') {
                Log::error('[Sentinel Multispectral STDERR] ' . $stderr);
            }

            if (!$process->isSuccessful()) {
                throw new \RuntimeException('Sentinel multispectral processing failed.');
            }

            if (!File::exists($outputPath)) {
                throw new \RuntimeException('Multispectral output was not created.');
            }

            $outputSize = File::size($outputPath) ?: $downloadedSize;

            $relativePath = 'public/imagery/' . $this->outputFilename;
            $geoserverData = null;

            try {
                $geoserverData = app(GeoServerService::class)->publishImageryLayer($imagery, $outputPath, 'source');
            } catch (Throwable $publishException) {
                Log::warning('ProcessSentinelSceneJob: Failed to publish Sentinel scene to GeoServer.', [
                    'imagery_id' => $this->imageryId,
                    'error' => $publishException->getMessage(),
                ]);
            }

            $updatePayload = [
                'upload_status' => 'done',
                'format' => pathinfo($this->outputFilename, PATHINFO_EXTENSION) ?: 'tif',
                'size' => $outputSize,
                'original_name' => $this->displayName,
                'path' => $relativePath,
            ];

            if ($geoserverData) {
                $updatePayload['geoserver_store_name'] = $geoserverData['store'];
                $updatePayload['geoserver_layer_name'] = $geoserverData['layer'];
                $updatePayload['geoserver_bounds'] = $geoserverData['bounds'] ?? null;
            }

            $imagery->update($updatePayload);

            Log::info('ProcessSentinelSceneJob completed successfully.', [
                'imagery_id' => $this->imageryId,
                'zip_path' => $zipPath,
                'output_path' => $outputPath,
            ]);

            ProcessImageryJob::dispatch($imagery->id)->onQueue('processing');
        } catch (Throwable $exception) {
            Log::error('ProcessSentinelSceneJob failed: ' . $exception->getMessage(), [
                'imagery_id' => $this->imageryId,
                'metadata' => $this->metadata,
            ]);

            if (File::exists($outputPath)) {
                File::delete($outputPath);
            }

            $creditService->refundCreditsForFailure($imagery, "Job");
            $imagery->update([
                'upload_status' => 'failed',
                'processing_status' => 'error',
            ]);
        }
    }
}
