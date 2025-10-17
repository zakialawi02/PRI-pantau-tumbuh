<?php

namespace App\Jobs;

use Throwable;
use App\Jobs\ProcessImageryJob;
use App\Models\ImageryData;
use Carbon\Carbon;
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
        $creditService = new CreditService();
        $pythonService = new PythonService();
        $imagery = ImageryData::find($this->imageryId);

        if (!$imagery) {
            Log::error('ProcessSentinelClipJob: Imagery record not found.', [
                'imagery_id' => $this->imageryId,
            ]);
            return;
        }

        $scene = $this->payload['scene'] ?? [];
        $geometry = $this->payload['geometry'] ?? null;

        if (!$geometry || !is_array($geometry)) {
            Log::error('ProcessSentinelClipJob: Geometry payload missing or invalid.', [
                'imagery_id' => $this->imageryId,
            ]);
            $imagery->update([
                'upload_status' => 'failed',
                'processing_status' => 'error',
            ]);
            $creditService->refundCreditsForFailure($imagery, 'ClipJob');
            return;
        }

        $scriptsBase = base_path('scripts');
        $scriptPath = $scriptsBase . DIRECTORY_SEPARATOR . 'process_clipped_multispectral_auto.py';
        $pythonPath = $pythonService->resolvePythonPath($scriptsBase);

        if (!$pythonPath || !File::exists($pythonPath)) {
            Log::error('ProcessSentinelClipJob: Python executable not found.', [
                'imagery_id' => $this->imageryId,
            ]);
            $imagery->update([
                'upload_status' => 'failed',
                'processing_status' => 'error',
            ]);
            $creditService->refundCreditsForFailure($imagery, 'ClipJob');
            return;
        }

        if (!File::exists($scriptPath)) {
            Log::error('ProcessSentinelClipJob: Processing script not found.', [
                'imagery_id' => $this->imageryId,
                'script_path' => $scriptPath,
            ]);
            $imagery->update([
                'upload_status' => 'failed',
                'processing_status' => 'error',
            ]);
            $creditService->refundCreditsForFailure($imagery, 'ClipJob');
            return;
        }

        $copernicusClientId = config('services.copernicus.client_id');
        $copernicusClientSecret = config('services.copernicus.client_secret');

        if (empty($copernicusClientId) || empty($copernicusClientSecret)) {
            Log::error('ProcessSentinelClipJob: Copernicus credentials missing.', [
                'imagery_id' => $this->imageryId,
            ]);
            $imagery->update([
                'upload_status' => 'failed',
                'processing_status' => 'error',
            ]);
            $creditService->refundCreditsForFailure($imagery, 'ClipJob');
            return;
        }

        $imagery->update(['processing_status' => 'processing']);

        $storageImageryDir = storage_path('app/public/imagery');
        File::ensureDirectoryExists($storageImageryDir);

        $outputPath = $storageImageryDir . DIRECTORY_SEPARATOR . $imagery->stored_name;
        if (File::exists($outputPath)) {
            File::delete($outputPath);
        }

        $workDir = storage_path('app/tmp/sentinel-clip/' . $imagery->id);
        $tilesDir = $workDir . DIRECTORY_SEPARATOR . 'tiles';

        File::ensureDirectoryExists($workDir);
        File::ensureDirectoryExists($tilesDir);

        $sceneDatetime = $scene['datetime'] ?? null;
        try {
            $sceneDatetime = $sceneDatetime ? Carbon::parse($sceneDatetime) : null;
        } catch (Throwable $exception) {
            $sceneDatetime = null;
        }

        $timeWindowMinutes = (int) ($this->payload['time_window_minutes'] ?? 120);
        $timeWindowMinutes = $timeWindowMinutes > 0 ? $timeWindowMinutes : 120;
        $halfWindow = (int) floor($timeWindowMinutes / 2);

        if ($sceneDatetime) {
            $timeFrom = $sceneDatetime->copy()->subMinutes($halfWindow > 0 ? $halfWindow : 60)->toIso8601String();
            $timeTo = $sceneDatetime->copy()->addMinutes($halfWindow > 0 ? $halfWindow : 60)->toIso8601String();
        } else {
            $timeTo = Carbon::now()->toIso8601String();
            $timeFrom = Carbon::now()->subDays(7)->toIso8601String();
        }

        $envOverrides = [
            'COPERNICUS_CLIENT_ID' => $copernicusClientId,
            'COPERNICUS_CLIENT_SECRET' => $copernicusClientSecret,
            'CLIP_GEOMETRY' => json_encode($geometry, JSON_UNESCAPED_SLASHES),
            'CLIP_OUTPUT_PATH' => $outputPath,
            'CLIP_TILE_DIR' => $tilesDir,
            'CLIP_COLLECTION' => strtolower($scene['collection'] ?? 'sentinel-2-l2a'),
            'CLIP_SCENE_ID' => (string) ($scene['id'] ?? ''),
            'CLIP_TIME_FROM' => $timeFrom,
            'CLIP_TIME_TO' => $timeTo,
            'CLIP_RESOLUTION' => (string) ($this->payload['resolution'] ?? 10),
            'CLIP_TILE_MAX_PX' => (string) ($this->payload['max_tile_px'] ?? 2500),
            'CLIP_NODATA' => (string) ($this->payload['nodata'] ?? 0),
            'PYTHONUNBUFFERED' => '1',
        ];

        $processEnv = $pythonService->buildProcessEnvironment($envOverrides);

        $process = new Process([$pythonPath, $scriptPath], $scriptsBase, $processEnv);
        $process->setTimeout(7200);

        try {
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
                throw new \RuntimeException('Sentinel clip output file was not generated.');
            }

            $sizeKb = File::size($outputPath) / 1024 ?: 0;

            $imagery->update([
                'upload_status' => 'done',
                'processing_status' => 'waiting',
                'size' => $sizeKb,
                'path' => 'storage/imagery/' . $imagery->stored_name,
            ]);

            Log::info('ProcessSentinelClipJob completed successfully.', [
                'imagery_id' => $imagery->id,
                'output' => $outputPath,
            ]);

            ProcessImageryJob::dispatch($imagery->id)->onQueue('processing');
        } catch (Throwable $exception) {
            Log::error('ProcessSentinelClipJob failed: ' . $exception->getMessage(), [
                'imagery_id' => $imagery->id,
            ]);

            if (File::exists($outputPath)) {
                File::delete($outputPath);
            }

            $imagery->update([
                'upload_status' => 'failed',
                'processing_status' => 'error',
            ]);

            $creditService->refundCreditsForFailure($imagery, 'ClipJob');
        } finally {
            if (File::isDirectory($workDir)) {
                try {
                    File::deleteDirectory($workDir);
                } catch (Throwable $cleanupException) {
                    Log::warning('ProcessSentinelClipJob cleanup warning: ' . $cleanupException->getMessage(), [
                        'imagery_id' => $this->imageryId,
                    ]);
                }
            }
        }
    }
}
