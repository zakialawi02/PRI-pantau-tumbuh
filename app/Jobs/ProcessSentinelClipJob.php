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
use Symfony\Component\Process\Process;
use Throwable;

class ProcessSentinelClipJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public string $imageryId;
    public array $options;

    /**
     * Create a new job instance.
     */
    public function __construct(string $imageryId, array $options)
    {
        $this->imageryId = $imageryId;
        $this->options = $options;
    }

    /**
     * Execute the job.
     */
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

        $scriptsBase = base_path('scripts');
        $pythonPath = $pythonService->resolvePythonPath($scriptsBase);
        $scriptPath = $scriptsBase . DIRECTORY_SEPARATOR . 'process_clipped_multispectral_auto.py';

        if (!$pythonPath || !File::exists($pythonPath)) {
            Log::error('ProcessSentinelClipJob: Python executable not found.');
            $this->markAsFailed($imagery, $creditService, 'Python executable not found.');
            return;
        }

        if (!File::exists($scriptPath)) {
            Log::error('ProcessSentinelClipJob: Clip processing script missing.', [
                'path' => $scriptPath,
            ]);
            $this->markAsFailed($imagery, $creditService, 'Clip processing script missing.');
            return;
        }

        $publicStorage = storage_path('app/public');
        $imageryDir = $publicStorage . DIRECTORY_SEPARATOR . 'imagery';
        File::makeDirectory($imageryDir, 0755, true, true);

        $jobWorkspace = storage_path('app/tmp/clip-' . $imagery->id);
        File::makeDirectory($jobWorkspace, 0755, true, true);

        $tileDir = $jobWorkspace . DIRECTORY_SEPARATOR . 'tiles';
        File::makeDirectory($tileDir, 0755, true, true);

        $mergedPath = $jobWorkspace . DIRECTORY_SEPARATOR . 'merged_fixed.tif';
        $maskedPath = $jobWorkspace . DIRECTORY_SEPARATOR . 'merged_masked.tif';
        $outputPath = $imageryDir . DIRECTORY_SEPARATOR . ($this->options['output_filename'] ?? ($imagery->id . '_clip.tif'));

        $geometryOption = $this->options['geometry'] ?? [];
        if (is_array($geometryOption) && ($geometryOption['type'] ?? '') !== 'FeatureCollection') {
            $geometryOption = [
                'type' => 'FeatureCollection',
                'features' => [
                    [
                        'type' => 'Feature',
                        'properties' => new \stdClass(),
                        'geometry' => $geometryOption,
                    ],
                ],
            ];
        }

        $overrides = [
            'AOI_GEOJSON' => json_encode($geometryOption),
            'DATE_FROM' => (string) ($this->options['date_from'] ?? ''),
            'DATE_TO' => (string) ($this->options['date_to'] ?? ''),
            'MAX_CLOUD' => (string) ($this->options['max_cloud'] ?? 60),
            'LIMIT' => (string) ($this->options['limit'] ?? 15),
            'RES' => (string) ($this->options['resolution'] ?? 10),
            'SCENE_ID' => (string) ($this->options['scene_id'] ?? ''),
            'OUTPUT_TIF' => $outputPath,
            'CLIP_TILE_DIR' => $tileDir,
            'CLIP_MERGED_TIF' => $mergedPath,
            'CLIP_MASKED_TIF' => $maskedPath,
        ];

        if (array_key_exists('nodata', $this->options)) {
            $overrides['NODATA_VAL'] = (string) $this->options['nodata'];
        }

        $processEnv = $pythonService->buildProcessEnvironment($overrides);

        try {
            $imagery->update([
                'processing_status' => 'processing',
                'upload_status' => 'uploading',
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

            if (!$process->isSuccessful() || !File::exists($outputPath)) {
                Log::error('ProcessSentinelClipJob: Clip processing failed or output missing.', [
                    'imagery_id' => $this->imageryId,
                    'output_path' => $outputPath,
                ]);
                $this->markAsFailed($imagery, $creditService, 'Clip processing failed or output missing.');
                return;
            }

            $size = File::size($outputPath) ?: 0;

            $imagery->update([
                'upload_status' => 'done',
                'processing_status' => 'completed',
                'format' => pathinfo($outputPath, PATHINFO_EXTENSION) ?: 'tif',
                'size' => $size,
                'path' => 'storage/imagery/' . basename($outputPath),
                'processed_path' => null,
                'processed_at' => null,
            ]);

            Log::info('ProcessSentinelClipJob completed successfully.', [
                'imagery_id' => $this->imageryId,
                'output_path' => $outputPath,
            ]);

            ProcessImageryJob::dispatch($imagery->id)->onQueue('processing');
        } catch (Throwable $exception) {
            Log::error('ProcessSentinelClipJob encountered an error: ' . $exception->getMessage(), [
                'imagery_id' => $this->imageryId,
            ]);
            $this->markAsFailed($imagery, $creditService, $exception->getMessage());
        } finally {
            if (File::isDirectory($jobWorkspace)) {
                File::deleteDirectory($jobWorkspace);
            }
        }
    }

    protected function markAsFailed(ImageryData $imagery, CreditService $creditService, string $reason): void
    {
        $creditService->refundCreditsForFailure($imagery, 'ClipJob');

        $imagery->update([
            'upload_status' => 'failed',
            'processing_status' => 'error',
        ]);

        Log::error('ProcessSentinelClipJob failed.', [
            'imagery_id' => $imagery->id,
            'reason' => $reason,
        ]);
    }
}

