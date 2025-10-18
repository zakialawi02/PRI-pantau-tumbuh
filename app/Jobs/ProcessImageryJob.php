<?php

namespace App\Jobs;

use App\Models\ImageryData;
use Illuminate\Bus\Queueable;
use App\Services\CreditService;
use App\Services\GeoServerService;
use App\Services\PythonService;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\File;
use Illuminate\Queue\SerializesModels;
use Symfony\Component\Process\Process;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Support\Str;
use Throwable;

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

            $imagery->update([
                'processing_status' => 'completed',
                'processed_path' => $publicPath,
                'processed_at' => now(),
            ]);

            if (File::exists($outputPath)) {
                $this->publishProcessedImagery($imagery, $outputPath);
                Log::info("✅ [Job] Processing completed successfully. Output file found at: {$outputPath}");
                return;
            }

            Log::warning("⚠️ [Job] Processing done, but output file not detected in expected location.");
        } else {
            // Refund credits when processing fails
            $creditService->refundCreditsForFailure($imagery, "Job");
            $imagery->update(['processing_status' => 'error']);
            Log::error("❌ [Job] Processing failed for {$imagery->id}");
        }
    }

    protected function publishProcessedImagery(ImageryData $imagery, string $filePath): void
    {
        if (!$this->isGeoTiff($filePath)) {
            return;
        }

        try {
            /** @var GeoServerService $geoServer */
            $geoServer = app(GeoServerService::class);
            $result = $geoServer->publishGeoTiff($filePath, [
                'store' => sprintf('imagery_%s_processed', Str::lower($imagery->id)),
                'coverage' => sprintf('imagery_%s_processed', Str::lower($imagery->id)),
                'title' => trim(($imagery->original_name ?: 'Imagery') . ' (Processed)'),
            ]);

            $updates = array_filter([
                'geoserver_workspace' => $result['workspace'] ?? null,
                'processed_geoserver_store' => $result['store'] ?? null,
                'processed_geoserver_layer' => $result['layer'] ?? null,
                'processed_wms_url' => $result['wms']['url'] ?? null,
                'processed_wmts_url' => $result['wmts']['url'] ?? null,
                'processed_bbox' => $result['bbox'] ?? null,
            ], static fn ($value) => $value !== null);

            if (!empty($updates)) {
                $imagery->update($updates);
            }
        } catch (Throwable $exception) {
            Log::error('ProcessImageryJob: Failed to publish processed imagery to GeoServer', [
                'imagery_id' => $imagery->id,
                'message' => $exception->getMessage(),
            ]);
        }
    }

    protected function isGeoTiff(string $path): bool
    {
        $extension = Str::lower(pathinfo($path, PATHINFO_EXTENSION));
        return in_array($extension, ['tif', 'tiff'], true);
    }
}
