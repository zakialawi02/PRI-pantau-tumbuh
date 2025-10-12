<?php

namespace App\Jobs;

use App\Models\ImageryData;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Symfony\Component\Process\Process;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;

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
        $imagery = ImageryData::find($this->imageryId);
        if (!$imagery) {
            Log::error("❌ [Job] Imagery not found: {$this->imageryId}");
            return;
        }

        // Ubah status jadi processing
        $imagery->update(['processing_status' => 'processing']);
        Log::info("🛰️ [Job] Processing started for imagery: {$imagery->id}");

        // Tentukan path python & script
        $base = base_path('scripts');
        $scriptPath = "{$base}/process_imagery.py";
        $filePath   = storage_path("app/public/citra/{$imagery->stored_name}");

        if (!File::exists($filePath)) {
            Log::error("❌ [Job] Input imagery file not found: {$filePath}");
            $this->refundCredits($imagery);
            $imagery->update(['processing_status' => 'error']);
            return;
        }

        $processedDirectory = storage_path('app/public/citra/processed');
        if (!File::isDirectory($processedDirectory)) {
            File::makeDirectory($processedDirectory, 0755, true, true);
        }

        $extension = pathinfo($imagery->stored_name, PATHINFO_EXTENSION) ?: 'tif';
        $processedFileName = pathinfo($imagery->stored_name, PATHINFO_FILENAME) . '_processed.' . $extension;
        $outputPath = $processedDirectory . DIRECTORY_SEPARATOR . $processedFileName;

        $modelPath = $base . DIRECTORY_SEPARATOR . 'Data Model' . DIRECTORY_SEPARATOR . 'Best_Model2.h5';
        $scalerPath = $base . DIRECTORY_SEPARATOR . 'Data Model' . DIRECTORY_SEPARATOR . 'Best_Scaler2.pkl';

        if (!File::exists($modelPath)) {
            Log::error("❌ [Job] Model file not found: {$modelPath}");
            $this->refundCredits($imagery);
            $imagery->update(['processing_status' => 'error']);
            return;
        }

        if (!File::exists($scalerPath)) {
            Log::error("❌ [Job] Scaler file not found: {$scalerPath}");
            $this->refundCredits($imagery);
            $imagery->update(['processing_status' => 'error']);
            return;
        }

        // Path Python di venv
        if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
            $pythonPath = "{$base}\\venv\\Scripts\\python.exe";
            if (!file_exists($pythonPath)) {
                $pythonPath = "{$base}\\.venv\\Scripts\\python.exe";
            }
        } else {
            $pythonPath = "{$base}/venv/bin/python";
            if (!file_exists($pythonPath)) {
                $pythonPath = "{$base}/.venv/bin/python";
            }
        }

        if (!file_exists($pythonPath)) {
            Log::error("❌ [Job] Python venv not found: {$pythonPath}");
            $this->refundCredits($imagery);
            $imagery->update(['processing_status' => 'error']);
            return;
        }

        if (!file_exists($scriptPath)) {
            Log::error("❌ [Job] Python script not found: {$scriptPath}");
            $this->refundCredits($imagery);
            $imagery->update(['processing_status' => 'error']);
            return;
        }

        // Jalankan proses Python
        $processEnv = $this->buildProcessEnvironment([
            'IMAGERY_INPUT_PATH' => $filePath,
            'IMAGERY_OUTPUT_PATH' => $outputPath,
            'IMAGERY_MODEL_PATH' => $modelPath,
            'IMAGERY_SCALER_PATH' => $scalerPath,
            'IMAGERY_ID' => (string) $imagery->id,
        ]);

        $process = new Process([$pythonPath, $scriptPath], $base, $processEnv);
        $process->setTimeout(7200); // max 2 jam
        $process->run();

        // 🔍 Debug output dari Python
        $stdout = trim($process->getOutput());
        $stderr = trim($process->getErrorOutput());

        Log::info("🐍 [Python STDOUT]: " . ($stdout ?: '[empty]'));
        if (!empty($stderr)) {
            Log::error("🐍 [Python STDERR]: " . $stderr);
        }

        // Log stdout / stderr ke laravel.log
        if ($process->getOutput()) {
            Log::info('[PYTHON OUT] ' . trim($process->getOutput()));
        }
        if ($process->getErrorOutput()) {
            Log::error('[PYTHON ERR] ' . trim($process->getErrorOutput()));
        }

        // Cek hasil eksekusi
        if ($process->isSuccessful()) {
            $publicPath = "storage/citra/processed/{$processedFileName}";

            if (File::exists($outputPath)) {
                $imagery->update([
                    'processing_status' => 'completed',
                    'processed_path' => $publicPath,
                    'processed_at' => now(),
                ]);

                Log::info("✅ [Job] Processing completed successfully. Output file found at: {$outputPath}");
                return;
            } else {
                // Simpan path-nya tetap, tapi log kalau file gak terdeteksi
                $imagery->update([
                    'processing_status' => 'completed',
                    'processed_path' => $publicPath,
                    'processed_at' => now(),
                ]);
                Log::warning("⚠️ [Job] Processing done, but output file not detected in expected location.");
            }
        } else {
            // Refund credits when processing fails
            $this->refundCredits($imagery);

            $imagery->update(['processing_status' => 'error']);
            Log::error("❌ [Job] Processing failed for {$imagery->id}");
        }
    }

    /**
     * Refund credits to user when processing fails
     */
    private function refundCredits($imagery)
    {
        try {
            // Get the user who owns this imagery
            $user = $imagery->user;
            if (!$user) {
                Log::error("❌ [Job] User not found for imagery: {$imagery->id}");
                return;
            }

            // Get user's credit record
            $userCredit = $user->credits;
            if (!$userCredit) {
                Log::error("❌ [Job] User credit record not found for user: {$user->id}");
                return;
            }

            // Get the credit cost from config
            $creditCost = config('app-constants.imagery_processing_cost', 10);

            // Refund the credits
            $userCredit->credits += $creditCost;
            $userCredit->save();

            Log::info("💰 [Job] Refunded {$creditCost} credits to user {$user->id} for failed imagery processing: {$imagery->id}");
        } catch (\Exception $e) {
            Log::error("❌ [Job] Failed to refund credits for imagery {$imagery->id}: " . $e->getMessage());
        }
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
