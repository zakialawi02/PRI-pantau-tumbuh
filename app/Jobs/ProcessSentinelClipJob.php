<?php

namespace App\Jobs;

use Throwable;
use App\Models\ImageryData;
use Illuminate\Bus\Queueable;
use App\Services\CreditService;
use App\Services\PythonService;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Symfony\Component\Process\Process;

class ProcessSentinelClipJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public string $imageryId;
    public array $options;
    public float $creditCost;

    public function __construct(string $imageryId, array $options, float $creditCost)
    {
        $this->imageryId = $imageryId;
        $this->options = $options;
        $this->creditCost = $creditCost;
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

        $creditService = new CreditService();
        $pythonService = new PythonService();

        $imagery->update([
            'processing_status' => 'processing',
        ]);

        $scriptsBase = base_path('scripts');
        $scriptPath = $scriptsBase . DIRECTORY_SEPARATOR . 'process_clipped_multispectral_auto.py';
        $pythonPath = $pythonService->resolvePythonPath($scriptsBase);

        if (!$pythonPath) {
            Log::error('ProcessSentinelClipJob: Python executable not found for Sentinel clip processing.');
            $creditService->refundCreditsForFailure($imagery, 'SentinelClipJob', $this->creditCost);
            $imagery->update(['processing_status' => 'error']);
            return;
        }

        if (!File::exists($scriptPath)) {
            Log::error('ProcessSentinelClipJob: Clip processing script missing.', [
                'path' => $scriptPath,
            ]);
            $creditService->refundCreditsForFailure($imagery, 'SentinelClipJob', $this->creditCost);
            $imagery->update(['processing_status' => 'error']);
            return;
        }

        $tempBase = storage_path('app/tmp/sentinel-clip/' . $this->imageryId);
        $tilesDir = $tempBase . '/tiles';
        $mergedPath = $tempBase . '/merged_fixed.tif';
        $maskedPath = $tempBase . '/merged_masked.tif';

        if (!File::isDirectory($tilesDir)) {
            File::makeDirectory($tilesDir, 0755, true, true);
        }

        $outputDir = storage_path('app/public/imagery');
        if (!File::isDirectory($outputDir)) {
            File::makeDirectory($outputDir, 0755, true, true);
        }

        $outputFilename = Arr::get($this->options, 'output_filename');
        $displayName = Arr::get($this->options, 'display_name');
        $outputPath = $outputDir . DIRECTORY_SEPARATOR . $outputFilename;

        $geometry = Arr::get($this->options, 'geometry');
        $geojson = [
            'type' => 'FeatureCollection',
            'features' => [
                [
                    'type' => 'Feature',
                    'properties' => Arr::get($this->options, 'geometry_properties', []),
                    'geometry' => $geometry,
                ],
            ],
        ];

        $filters = Arr::get($this->options, 'filters', []);
        $selectedScene = Arr::get($this->options, 'selected_scene');
        $mode = Arr::get($this->options, 'mode', 'auto');

        $envOverrides = [
            'SH_CLIENT_ID' => (string) (env('SENTINEL_HUB_CLIENT_ID') ?? env('COPERNICUS_CLIENT_ID', '')),
            'SH_CLIENT_SECRET' => (string) (env('SENTINEL_HUB_CLIENT_SECRET') ?? env('COPERNICUS_CLIENT_SECRET', '')),
            'CLIP_TILES_DIR' => $tilesDir,
            'CLIP_MERGED_TIF' => $mergedPath,
            'CLIP_OUTPUT_TIF' => $maskedPath,
            'CLIP_AOI_GEOJSON' => json_encode($geojson, JSON_UNESCAPED_SLASHES),
            'CLIP_DATE_FROM' => Arr::get($filters, 'start_date', ''),
            'CLIP_DATE_TO' => Arr::get($filters, 'end_date', ''),
            'CLIP_MAX_CLOUD' => (string) Arr::get($filters, 'cloud_cover', ''),
            'CLIP_COLLECTION_TYPE' => Arr::get($filters, 'product_level', 'S2MSI2A'),
            'CLIP_MODE' => $mode,
        ];

        if ($selectedScene) {
            $envOverrides['CLIP_SCENE_ID'] = Arr::get($selectedScene, 'id', '');
            $envOverrides['CLIP_PRODUCT_ID'] = Arr::get($selectedScene, 'product_id', '');
            $envOverrides['CLIP_SCENE_DATETIME'] = Arr::get($selectedScene, 'acquisition_date', '');
        }

        $processEnv = $pythonService->buildProcessEnvironment($envOverrides);

        $process = new Process([$pythonPath, $scriptPath], $scriptsBase, $processEnv, null, 7200);

        try {
            $process->mustRun();

            if (!File::exists($maskedPath)) {
                throw new \RuntimeException('Clip processing did not produce the expected output.');
            }

            if (File::exists($outputPath)) {
                File::delete($outputPath);
            }

            File::move($maskedPath, $outputPath);

            $sizeBytes = File::exists($outputPath) ? File::size($outputPath) : 0;
            $relativePath = 'storage/imagery/' . $outputFilename;

            $imagery->update([
                'upload_status' => 'done',
                'processing_status' => 'completed',
                'format' => pathinfo($outputFilename, PATHINFO_EXTENSION) ?: 'tif',
                'size' => $sizeBytes,
                'path' => $relativePath,
                'processed_path' => $relativePath,
                'processed_at' => now(),
                'original_name' => $displayName . '.tif',
                'stored_name' => $outputFilename,
            ]);
        } catch (Throwable $throwable) {
            Log::error('ProcessSentinelClipJob failed.', [
                'imagery_id' => $this->imageryId,
                'message' => $throwable->getMessage(),
            ]);

            $imagery->update(['processing_status' => 'error']);
            $creditService->refundCreditsForFailure($imagery, 'SentinelClipJob', $this->creditCost);

            throw $throwable;
        } finally {
            File::deleteDirectory($tempBase);
        }
    }
}

