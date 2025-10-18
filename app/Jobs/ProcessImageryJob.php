<?php

namespace App\Jobs;

use App\Models\ImageryData;
use Illuminate\Bus\Queueable;
use App\Services\CreditService;
use App\Services\GeoServerService;
use App\Services\PythonService;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Illuminate\Queue\SerializesModels;
use Symfony\Component\Process\Process;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;

class ProcessImageryJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $imageryId;

    public function __construct($imageryId)
    {
        $this->imageryId = $imageryId;
    }

    public function handle()
    {
        $creditService = new CreditService();
        $pythonService = new PythonService();

        $imagery = ImageryData::find($this->imageryId);
        if (!$imagery) {
            Log::error("❌ [Job] Imagery not found: {$this->imageryId}");
            return;
        }

        // Ubah status jadi processing
        $imagery->update(['processing_status' => 'processing']);
        Log::info("🛰️ [Job] Processing started for imagery: {$imagery->id}");

        // Tentukan path python & script
        $scriptsBase = base_path('scripts');
        $scriptPath = "{$scriptsBase}/process_imagery.py";
        $filePath   = storage_path("app/public/imagery/{$imagery->stored_name}");

        if (!File::exists($filePath)) {
            Log::error("❌ [Job] Input imagery file not found: {$filePath}");
            $creditService->refundCreditsForFailure($imagery, "Job");
            $imagery->update(['processing_status' => 'error']);
            return;
        }

        $processedDirectory = storage_path('app/public/imagery/processed');
        if (!File::isDirectory($processedDirectory)) {
            File::makeDirectory($processedDirectory, 0755, true, true);
        }

        $extension = pathinfo($imagery->stored_name, PATHINFO_EXTENSION) ?: 'tif';
        $processedFileName = pathinfo($imagery->stored_name, PATHINFO_FILENAME) . '_processed.' . $extension;
        $outputPath = $processedDirectory . DIRECTORY_SEPARATOR . $processedFileName;

        $modelPath = $scriptsBase . DIRECTORY_SEPARATOR . 'Data Model' . DIRECTORY_SEPARATOR . 'Best_Model2.h5';
        $scalerPath = $scriptsBase . DIRECTORY_SEPARATOR . 'Data Model' . DIRECTORY_SEPARATOR . 'Best_Scaler2.pkl';

        if (!File::exists($modelPath)) {
            Log::error("❌ [Job] Model file not found: {$modelPath}");
            $creditService->refundCreditsForFailure($imagery, "Job");
            $imagery->update(['processing_status' => 'error']);
            return;
        }

        if (!File::exists($scalerPath)) {
            Log::error("❌ [Job] Scaler file not found: {$scalerPath}");
            $creditService->refundCreditsForFailure($imagery, "Job");
            $imagery->update(['processing_status' => 'error']);
            return;
        }

        // Path Python di venv
        $pythonPath = $pythonService->resolvePythonPath($scriptsBase);

        if (!file_exists($pythonPath)) {
            Log::error("❌ [Job] Python venv not found: {$pythonPath}");
            $creditService->refundCreditsForFailure($imagery, "Job");
            $imagery->update(['processing_status' => 'error']);
            return;
        }

        if (!file_exists($scriptPath)) {
            Log::error("❌ [Job] Python script not found: {$scriptPath}");
            $creditService->refundCreditsForFailure($imagery, "Job");
            $imagery->update(['processing_status' => 'error']);
            return;
        }

        // Konfigurasi tambahan untuk inferensi Python (opsional via .env)
        $tileSize = env('IMAGERY_TILE_SIZE');
        $batchSize = env('IMAGERY_BATCH_SIZE');

        $overrides = [
            'IMAGERY_INPUT_PATH' => $filePath,
            'IMAGERY_OUTPUT_PATH' => $outputPath,
            'IMAGERY_MODEL_PATH' => $modelPath,
            'IMAGERY_SCALER_PATH' => $scalerPath,
            'IMAGERY_ID' => (string) $imagery->id,
        ];

        if (!is_null($tileSize)) {
            $overrides['IMAGERY_TILE_SIZE'] = (string) $tileSize;
        }

        if (!is_null($batchSize)) {
            $overrides['IMAGERY_BATCH_SIZE'] = (string) $batchSize;
        }

        // Jalankan proses Python
        $processEnv = $pythonService->buildProcessEnvironment($overrides);

        $process = new Process([$pythonPath, $scriptPath], $scriptsBase, $processEnv);
        $process->setTimeout(7200); // max 2 jam
        $process->run();

        // Log stdout / stderr ke laravel.log
        if ($process->getOutput()) {
            Log::info('[PYTHON OUT] ' . trim($process->getOutput()));
        }
        if ($process->getErrorOutput()) {
            Log::error('[PYTHON ERR] ' . trim($process->getErrorOutput()));
        }

        // Cek hasil eksekusi
        if ($process->isSuccessful()) {
            $publicPath = "storage/imagery/processed/{$processedFileName}";
            $hasOutputFile = File::exists($outputPath);

            $updatePayload = [
                'processing_status' => 'completed',
                'processed_path' => $publicPath,
                'processed_at' => now(),
            ];

            if ($hasOutputFile) {
                try {
                    $geoserverUrl = config('geoserver.url');
                    if (empty($geoserverUrl)) {
                        Log::warning('⚠️ [Job] GeoServer publish skipped due to missing configuration.');
                    } else {
                        $geoServer = app(GeoServerService::class);
                        $nameSeed = 'imagery_' . Str::slug($imagery->id, '_');

                        $publications = [
                            'source' => null,
                            'processed' => null,
                        ];

                        $sourceStore = $nameSeed . '_source';
                        $processedStore = $nameSeed . '_processed';

                        try {
                            $publications['source'] = $geoServer->publishGeoTiff(
                                $sourceStore,
                                $sourceStore,
                                $filePath,
                                [
                                    'enabled' => true,
                                    'projection_policy' => 'FORCE_DECLARED',
                                ]
                            );

                            Log::info('🗺️ [Job] GeoServer source layer published.', [
                                'imagery_id' => $imagery->id,
                                'store' => $publications['source']['store'] ?? null,
                                'layer' => $publications['source']['layer'] ?? null,
                            ]);
                        } catch (\Throwable $publicationException) {
                            Log::error('❌ [Job] Failed to publish GeoServer source layer.', [
                                'imagery_id' => $imagery->id,
                                'error' => $publicationException->getMessage(),
                            ]);
                        }

                        try {
                            $publications['processed'] = $geoServer->publishGeoTiff(
                                $processedStore,
                                $processedStore,
                                $outputPath,
                                [
                                    'enabled' => true,
                                    'projection_policy' => 'FORCE_DECLARED',
                                ]
                            );

                            Log::info('🗺️ [Job] GeoServer processed layer published.', [
                                'imagery_id' => $imagery->id,
                                'store' => $publications['processed']['store'] ?? null,
                                'layer' => $publications['processed']['layer'] ?? null,
                            ]);
                        } catch (\Throwable $publicationException) {
                            Log::error('❌ [Job] Failed to publish GeoServer processed layer.', [
                                'imagery_id' => $imagery->id,
                                'error' => $publicationException->getMessage(),
                            ]);
                        }

                        $sourcePublishedAt = !empty($publications['source']) ? now() : null;
                        $processedPublishedAt = !empty($publications['processed']) ? now() : null;

                        $updatePayload['geoserver_source_store'] = $publications['source']['store'] ?? null;
                        $updatePayload['geoserver_source_layer'] = $publications['source']['layer'] ?? null;
                        $updatePayload['geoserver_source_bbox'] = $publications['source']['bounding_box'] ?? null;
                        $updatePayload['geoserver_source_published_at'] = $sourcePublishedAt;

                        $updatePayload['geoserver_processed_store'] = $publications['processed']['store'] ?? null;
                        $updatePayload['geoserver_processed_layer'] = $publications['processed']['layer'] ?? null;
                        $updatePayload['geoserver_processed_bbox'] = $publications['processed']['bounding_box'] ?? null;
                        $updatePayload['geoserver_processed_published_at'] = $processedPublishedAt;

                        // Maintain backwards compatibility for any consumers expecting single layer metadata.
                        $updatePayload['geoserver_store'] = $updatePayload['geoserver_processed_store']
                            ?? $updatePayload['geoserver_source_store'];
                        $updatePayload['geoserver_layer'] = $updatePayload['geoserver_processed_layer']
                            ?? $updatePayload['geoserver_source_layer'];
                        $updatePayload['geoserver_bbox'] = $updatePayload['geoserver_processed_bbox']
                            ?? $updatePayload['geoserver_source_bbox'];
                        $updatePayload['geoserver_published_at'] = $processedPublishedAt ?? $sourcePublishedAt;
                    }
                } catch (\Throwable $exception) {
                    Log::error('❌ [Job] Failed to publish GeoServer layers.', [
                        'imagery_id' => $imagery->id,
                        'error' => $exception->getMessage(),
                    ]);
                }
            }

            $imagery->update($updatePayload);

            if ($hasOutputFile) {
                Log::info("✅ [Job] Processing completed successfully. Output file found at: {$outputPath}");
            } else {
                Log::warning("⚠️ [Job] Processing done, but output file not detected in expected location.");
            }

            return;
        } else {
            // Refund credits when processing fails
            $creditService->refundCreditsForFailure($imagery, "Job");
            $imagery->update(['processing_status' => 'error']);
            Log::error("❌ [Job] Processing failed for {$imagery->id}");
        }
    }
}
