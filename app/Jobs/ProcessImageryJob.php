<?php

namespace App\Jobs;

use Throwable;
use App\Models\ImageryData;
use App\Services\CreditService;
use App\Services\GeoServerService;
use App\Services\PythonService;
use Illuminate\Bus\Queueable;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Queue\SerializesModels;
use Symfony\Component\Process\Process;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;

class ProcessImageryJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $imageryId;
    protected $payload;

    public function __construct($imageryId, array $payload = [])
    {
        $this->imageryId = $imageryId;
        $this->payload = $payload;
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
        $creditCost = $this->payload['required_credits'] ?? 0;

        $processedDirectory = storage_path('app/public/imagery/processed');
        if (!File::isDirectory($processedDirectory)) {
            File::makeDirectory($processedDirectory, 0755, true, true);
        }

        $extension = pathinfo($imagery->stored_name, PATHINFO_EXTENSION) ?: 'tif';
        $processedFileName = pathinfo($imagery->stored_name, PATHINFO_FILENAME) . '_processed.' . $extension;
        $outputPath = $processedDirectory . DIRECTORY_SEPARATOR . $processedFileName;

        try {
            if (!File::exists($filePath)) {
                Log::error("❌ [Job] Input imagery file not found: {$filePath}");
                throw new \RuntimeException("Input imagery file not found: {$filePath}");
            }

            $modelPath = $scriptsBase . DIRECTORY_SEPARATOR . 'Data Model' . DIRECTORY_SEPARATOR . 'Best_Model2.h5';
            $scalerPath = $scriptsBase . DIRECTORY_SEPARATOR . 'Data Model' . DIRECTORY_SEPARATOR . 'Best_Scaler2.pkl';

            if (!File::exists($modelPath)) {
                Log::error("❌ [Job] Model file not found: {$modelPath}");
                throw new \RuntimeException("Model file not found: {$modelPath}");
            }

            if (!File::exists($scalerPath)) {
                Log::error("❌ [Job] Scaler file not found: {$scalerPath}");
                throw new \RuntimeException("Scaler file not found: {$scalerPath}");
            }

            // Path Python di venv
            $pythonPath = $pythonService->resolvePythonPath($scriptsBase);

            if (!file_exists($pythonPath)) {
                Log::error("❌ [Job] Python venv not found: {$pythonPath}");
                throw new \RuntimeException("Python venv not found: {$pythonPath}");
            }

            if (!file_exists($scriptPath)) {
                Log::error("❌ [Job] Python script not found: {$scriptPath}");
                throw new \RuntimeException("Python script not found: {$scriptPath}");
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
            $stdout = trim($process->getOutput());
            if ($stdout !== '') {
                Log::info('[PYTHON OUT] ' . $stdout);
            }

            $stderr = trim($process->getErrorOutput());
            if ($stderr !== '') {
                Log::error('[PYTHON ERR] ' . $stderr);
            }

            // Cek hasil eksekusi
            if (!$process->isSuccessful()) {
                throw new \RuntimeException("Python processing failed for imagery: {$imagery->id}");
            }

            $geoserverService = app(GeoServerService::class);
            $relativePath = "storage/imagery/processed/{$processedFileName}";
            $geoserverData = null;

            if (File::exists($outputPath)) {
                try {
                    $geoserverData = $geoserverService->publishImageryLayer($imagery, $outputPath, 'processed');
                } catch (Throwable $geoserverException) {
                    Log::error('ProcessImageryJob: Failed to publish processed imagery to GeoServer.', [
                        'imagery_id' => $this->imageryId,
                        'error' => $geoserverException->getMessage(),
                    ]);
                }

                $imagery->update([
                    'processing_status' => 'completed',
                    'processed_path' => $relativePath,
                    'processed_at' => now(),
                    'processed_geoserver_store_name' => $geoserverData['store'] ?? $imagery->processed_geoserver_store_name,
                    'processed_geoserver_layer_name' => $geoserverData['layer'] ?? $imagery->processed_geoserver_layer_name,
                    'processed_geoserver_bounds' => $geoserverData['bounds'] ?? $imagery->processed_geoserver_bounds,
                ]);

                Log::info("✅ [Job] Processing completed successfully. Output file found at: {$outputPath}");
            } else {
                // Simpan path-nya tetap, tapi log kalau file gak terdeteksi
                $imagery->update([
                    'processing_status' => 'completed',
                    'processed_path' => $relativePath,
                    'processed_at' => now(),
                    'processed_geoserver_store_name' => $geoserverData['store'] ?? $imagery->processed_geoserver_store_name,
                    'processed_geoserver_layer_name' => $geoserverData['layer'] ?? $imagery->processed_geoserver_layer_name,
                    'processed_geoserver_bounds' => $geoserverData['bounds'] ?? $imagery->processed_geoserver_bounds,
                ]);
                Log::warning("⚠️ [Job] Processing done, but output file not detected in expected location.");
            }
        } catch (Throwable $exception) {
            Log::error("❌ [Job] Processing failed for {$imagery->id}: " . $exception->getMessage(), [
                'exception' => $exception,
                'payload' => $this->payload,
            ]);

            // Refund credits when processing fails
            $creditService->refundCreditsForFailure($imagery, $creditCost, "Job");
            $imagery->update(['processing_status' => 'error']);
        }
    }
}
