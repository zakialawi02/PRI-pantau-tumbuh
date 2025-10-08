<?php

namespace App\Jobs;

use App\Models\ImageryData;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Symfony\Component\Process\Process;
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

        // Path Python di venv
        if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
            $pythonPath = "{$base}\\venv\\Scripts\\python.exe";
        } else {
            $pythonPath = "{$base}/venv/bin/python";
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
        $process = new Process([$pythonPath, $scriptPath, $filePath, $imagery->id]);
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
            // Nama file hasil proses
            $processedFileName = pathinfo($imagery->stored_name, PATHINFO_FILENAME) . '_processed.' . pathinfo($imagery->stored_name, PATHINFO_EXTENSION);

            // Lokasi absolut di server
            $primaryPath = storage_path("app/public/citra/processed/{$processedFileName}");
            $fallbackPath = public_path("storage/citra/processed/{$processedFileName}");

            // Path yang disimpan ke DB (yang bisa diakses via browser)
            $publicPath = "storage/citra/processed/{$processedFileName}";

            $foundPath = null;
            if (file_exists($primaryPath)) {
                Log::info("🔍 [Job Debug] Expecting processed file at: {$primaryPath}");

                $foundPath = $primaryPath;
            } elseif (file_exists($fallbackPath)) {
                $foundPath = $fallbackPath;
            }

            if ($foundPath) {
                $imagery->update([
                    'processing_status' => 'completed',
                    'processed_path' => $publicPath,
                    'processed_at' => now(),
                ]);

                Log::info("✅ [Job] Processing completed successfully. Output file found at: {$foundPath}");
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
}
