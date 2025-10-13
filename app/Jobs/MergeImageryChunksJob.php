<?php

namespace App\Jobs;

use App\Models\ImageryData;
use App\Jobs\ProcessImageryJob;
use Exception;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;


class MergeImageryChunksJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * The ID of the imagery record.
     */
    public int $imageryId;

    /**
     * The directory containing uploaded chunks.
     */
    public string $chunkDirectory;

    /**
     * Absolute path where the final file should be written.
     */
    public string $finalPath;

    /**
     * Number of chunks expected for this upload.
     */
    public int $totalChunks;

    /**
     * Whether imagery processing should be skipped after merging.
     */
    public bool $skipProcessing;

    /**
     * Stored filename for the merged imagery file.
     */
    public string $storedName;

    /**
     * Create a new job instance.
     */
    public function __construct(
        int $imageryId,
        string $chunkDirectory,
        string $finalPath,
        int $totalChunks,
        bool $skipProcessing,
        string $storedName
    ) {
        $this->imageryId = $imageryId;
        $this->chunkDirectory = $chunkDirectory;
        $this->finalPath = $finalPath;
        $this->totalChunks = $totalChunks;
        $this->skipProcessing = $skipProcessing;
        $this->storedName = $storedName;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $imagery = ImageryData::find($this->imageryId);

        if (!$imagery) {
            Log::warning("MergeImageryChunksJob: Imagery record {$this->imageryId} not found.");
            return;
        }

        if (!File::isDirectory($this->chunkDirectory)) {
            Log::error("MergeImageryChunksJob: Chunk directory missing for imagery {$this->imageryId}.");
            $imagery->update([
                'upload_status' => 'failed',
                'processing_status' => 'error',
            ]);
            return;
        }

        try {
            if (!File::exists(dirname($this->finalPath))) {
                File::makeDirectory(dirname($this->finalPath), 0777, true, true);
            }

            $output = fopen($this->finalPath, 'wb');
            if (!$output) {
                throw new Exception('Unable to open destination file for writing.');
            }

            for ($i = 0; $i < $this->totalChunks; $i++) {
                $chunkPath = $this->chunkDirectory . DIRECTORY_SEPARATOR . 'chunk_' . $i;

                if (!File::exists($chunkPath)) {
                    throw new Exception("Missing chunk {$i} during merge.");
                }

                $input = fopen($chunkPath, 'rb');
                if (!$input) {
                    throw new Exception("Unable to open chunk {$i} for reading.");
                }

                while (!feof($input)) {
                    $buffer = fread($input, 1048576);
                    if ($buffer === false) {
                        fclose($input);
                        throw new Exception("Failed reading chunk {$i} data.");
                    }

                    if (fwrite($output, $buffer) === false) {
                        fclose($input);
                        throw new Exception("Failed writing chunk {$i} data.");
                    }
                }

                fclose($input);
                File::delete($chunkPath);
            }

            fclose($output);

            File::deleteDirectory($this->chunkDirectory);

            $fileSize = File::exists($this->finalPath) ? File::size($this->finalPath) : null;

            $updates = [
                'upload_status' => 'done',
                'processing_status' => $this->skipProcessing ? 'skip' : 'waiting',
                'path' => 'storage/imagery/' . $this->storedName,
            ];

            if ($fileSize !== null) {
                $updates['size'] = $fileSize;
            }

            $imagery->update($updates);

            if (!$this->skipProcessing) {
                ProcessImageryJob::dispatch($imagery->id);
            }
        } catch (Exception $exception) {
            Log::error('MergeImageryChunksJob failed: ' . $exception->getMessage(), [
                'imagery_id' => $this->imageryId,
            ]);

            if (File::exists($this->finalPath)) {
                File::delete($this->finalPath);
            }

            if (File::isDirectory($this->chunkDirectory)) {
                File::deleteDirectory($this->chunkDirectory);
            }

            $imagery->update([
                'upload_status' => 'failed',
                'processing_status' => 'error',
            ]);
        }
    }
}
