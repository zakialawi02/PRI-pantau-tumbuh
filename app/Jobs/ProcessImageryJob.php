<?php

namespace App\Jobs;

use App\Models\ImageryData;
use Illuminate\Bus\Queueable;
use App\Services\CreditService;
use App\Services\PythonService;
use App\Services\GeoServerService;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\File;
use Illuminate\Queue\SerializesModels;
use Symfony\Component\Process\Process;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Support\Str;

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
            $updatePayload = [
                'processing_status' => 'completed',
                'processed_path' => $publicPath,
                'processed_at' => now(),
            ];

            if (File::exists($outputPath)) {
                $geoServerConfigured = (bool) config('geoserver.url');

                if ($geoServerConfigured) {
                    try {
                        /** @var GeoServerService $geoServer */
                        $geoServer = app(GeoServerService::class);

                        $identifier = Str::slug('imagery_' . $imagery->id, '_');
                        $coverageOptions = [
                            'title' => $imagery->original_name,
                            'metadata' => [
                                'entry' => [
                                    [
                                        '@key' => 'imagery_id',
                                        '$' => (string) $imagery->id,
                                    ],
                                ],
                            ],
                        ];

                        $defaultSrs = config('geoserver.default_srs');
                        $projectionPolicy = config('geoserver.projection_policy');

                        if (! empty($defaultSrs)) {
                            $coverageOptions['srs'] = $defaultSrs;
                            $coverageOptions['nativeCRS'] = $defaultSrs;
                            $coverageOptions['wms_params']['SRS'] = $defaultSrs;
                            $coverageOptions['wms_params']['CRS'] = $defaultSrs;
                        }

                        if (! empty($projectionPolicy)) {
                            $coverageOptions['projectionPolicy'] = $projectionPolicy;
                        }

                        $publication = $geoServer->publishGeoTiff($identifier, $outputPath, $identifier, null, $coverageOptions);

                        $updatePayload = array_merge($updatePayload, [
                            'geoserver_store' => data_get($publication, 'store'),
                            'geoserver_layer' => data_get($publication, 'layer'),
                            'geoserver_wms_url' => data_get($publication, 'wms.base_url'),
                            'geoserver_wms_params' => data_get($publication, 'wms.params'),
                            'geoserver_wmts_url' => data_get($publication, 'wmts.base_url'),
                            'geoserver_wmts_layer' => data_get($publication, 'wmts.layer'),
                            'geoserver_native_bbox' => data_get($publication, 'bbox.native'),
                            'geoserver_latlon_bbox' => data_get($publication, 'bbox.latlon'),
                            'geoserver_published_at' => now(),
                            'geoserver_error' => null,
                        ]);

                        Log::info('✅ [Job] GeoServer publication completed.', [
                            'imagery_id' => $imagery->id,
                            'store' => data_get($publication, 'store'),
                            'layer' => data_get($publication, 'layer'),
                        ]);
                    } catch (\Throwable $geoServerException) {
                        $message = $geoServerException->getMessage();
                        $updatePayload['geoserver_error'] = $message;

                        Log::error('❌ [Job] Failed to publish processed imagery to GeoServer.', [
                            'imagery_id' => $imagery->id,
                            'error' => $message,
                            'trace' => $geoServerException->getTraceAsString(),
                        ]);
                    }
                } else {
                    Log::warning('⚠️ [Job] GeoServer configuration missing. Skipping publication.', [
                        'imagery_id' => $imagery->id,
                    ]);
                    $updatePayload['geoserver_error'] = 'GeoServer configuration missing.';
                }

                Log::info("✅ [Job] Processing completed successfully. Output file found at: {$outputPath}");
            } else {
                Log::warning("⚠️ [Job] Processing done, but output file not detected in expected location.");
            }

            $imagery->update($updatePayload);

            if (File::exists($outputPath)) {
                return;
            }
        } else {
            // Refund credits when processing fails
            $creditService->refundCreditsForFailure($imagery, "Job");
            $imagery->update(['processing_status' => 'error']);
            Log::error("❌ [Job] Processing failed for {$imagery->id}");
        }
    }
}
