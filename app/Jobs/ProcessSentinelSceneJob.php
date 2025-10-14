<?php

namespace App\Jobs;

use App\Models\ImageryData;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Symfony\Component\Process\Process;
use Throwable;

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
        $imagery = ImageryData::find($this->imageryId);

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
                'connect_timeout' => 30,
            ])->get($this->downloadUrl);

            if ($response->failed()) {
                throw new \RuntimeException('Unable to download Sentinel scene. HTTP status: ' . $response->status());
            }

            if (!File::exists($zipPath)) {
                throw new \RuntimeException('Sentinel scene download missing after request.');
            }

            $downloadedSize = File::size($zipPath) ?: 0;

            if (File::exists($outputPath)) {
                File::delete($outputPath);
            }

            $scriptsBase = base_path('scripts');
            $scriptPath = $scriptsBase . DIRECTORY_SEPARATOR . 'process_to_multispectral_auto.py';
            $pythonPath = $this->resolvePythonPath($scriptsBase);

            if (!$pythonPath) {
                throw new \RuntimeException('Python executable for Sentinel processing not found.');
            }

            if (!File::exists($scriptPath)) {
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

            $processEnv = $this->buildProcessEnvironment($overrides);

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

            $imagery->update([
                'upload_status' => 'done',
                'format' => pathinfo($this->outputFilename, PATHINFO_EXTENSION) ?: 'tif',
                'size' => $outputSize,
                'original_name' => $this->displayName,
                'path' => 'storage/imagery/' . $this->outputFilename,
            ]);

            Log::info('ProcessSentinelSceneJob completed successfully.', [
                'imagery_id' => $this->imageryId,
                'zip_path' => $zipPath,
                'output_path' => $outputPath,
            ]);

            ProcessImageryJob::dispatch($imagery->id);
        } catch (Throwable $exception) {
            Log::error('ProcessSentinelSceneJob failed: ' . $exception->getMessage(), [
                'imagery_id' => $this->imageryId,
                'metadata' => $this->metadata,
            ]);

            if (File::exists($outputPath)) {
                File::delete($outputPath);
            }

            $imagery->update([
                'upload_status' => 'failed',
                'processing_status' => 'error',
            ]);
        }
    }

    private function resolvePythonPath(string $basePath): ?string
    {
        if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
            $candidates = [
                $basePath . '\\venv\\Scripts\\python.exe',
                $basePath . '\\.venv\\Scripts\\python.exe',
            ];
        } else {
            $candidates = [
                $basePath . '/venv/bin/python',
                $basePath . '/.venv/bin/python',
            ];
        }

        foreach ($candidates as $candidate) {
            if (is_file($candidate)) {
                return $candidate;
            }
        }

        return null;
    }

    private function buildProcessEnvironment(array $overrides = []): array
    {
        $baseEnv = [];

        foreach (array_merge($_ENV, $_SERVER) as $key => $value) {
            if (is_string($key) && !array_key_exists($key, $baseEnv)) {
                $baseEnv[$key] = $value;
            }
        }

        foreach ($overrides as $key => $value) {
            $baseEnv[$key] = $value;
        }

        return $baseEnv;
    }
}

