<?php

namespace App\Http\Controllers;

use App\Jobs\ProcessSentinelSceneJob;
use App\Models\ImageryData;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

class SentinelProcessingController extends Controller
{
    public function store(Request $request)
    {
        $user = $request->user();

        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'download_url' => ['required', 'url'],
            'product_id' => ['nullable', 'string', 'max:255'],
            'collection' => ['nullable', 'string', 'max:255'],
            'acquisition_date' => ['nullable', 'string', 'max:255'],
            'download_filename' => ['nullable', 'string', 'max:255'],
        ]);

        $rawTitle = trim($validated['title'] ?? '') ?: 'Sentinel Scene';
        $displayTitle = $this->sanitizeDisplayName($rawTitle);
        $finalDisplayName = $this->buildDisplayName($displayTitle, 'tif');

        $slugBase = Str::slug(Str::limit($rawTitle, 120, ''));
        if ($slugBase === '') {
            $slugBase = 'sentinel-scene';
        }

        $disk = Storage::disk('public');
        $imageryDirectory = 'imagery';
        $sentinelDirectory = 'imagery/sentinel';

        $disk->makeDirectory($imageryDirectory);
        $disk->makeDirectory($sentinelDirectory);

        $zipFilename = $this->ensureUniqueFilename($sentinelDirectory, $slugBase, 'zip');
        $outputBase = Str::limit($slugBase . '-multispectral', 160, '');
        $outputFilename = $this->ensureUniqueFilename($imageryDirectory, $outputBase, 'tif');

        try {
            $imagery = ImageryData::create([
                'user_id' => $user->id,
                'source_type' => 'sentinel-2',
                'original_name' => $finalDisplayName,
                'stored_name' => $outputFilename,
                'size' => 0,
                'format' => 'zip',
                'path' => 'storage/' . trim($imageryDirectory, '/') . '/' . $outputFilename,
                'upload_status' => 'uploading',
                'processing_status' => 'waiting',
                'uploaded_at' => now(),
            ]);

            ProcessSentinelSceneJob::dispatch(
                $imagery->id,
                $validated['download_url'],
                $sentinelDirectory,
                $zipFilename,
                $outputFilename,
                $finalDisplayName,
                [
                    'product_id' => $validated['product_id'] ?? null,
                    'collection' => $validated['collection'] ?? null,
                    'acquisition_date' => $validated['acquisition_date'] ?? null,
                    'download_filename' => $validated['download_filename'] ?? null,
                ]
            );

            return response()->json([
                'success' => true,
                'message' => 'Sentinel scene queued for processing.',
                'data' => [
                    'id' => $imagery->id,
                    'upload_status' => $imagery->upload_status,
                    'processing_status' => $imagery->processing_status,
                ],
            ], 202);
        } catch (Throwable $exception) {
            Log::error('SentinelProcessingController@store failed to queue processing.', [
                'error' => $exception->getMessage(),
                'trace' => $exception->getTraceAsString(),
            ]);

            if (isset($imagery)) {
                $imagery->update([
                    'upload_status' => 'failed',
                    'processing_status' => 'error',
                ]);
            }

            return response()->json([
                'success' => false,
                'message' => 'Unable to queue Sentinel imagery processing at this time.',
            ], 500);
        }
    }

    private function sanitizeDisplayName(string $value): string
    {
        $cleaned = str_replace([
            '\\',
            '/',
            ':',
            "\"",
            '*',
            '?',
            '<',
            '>',
            '|',
        ], ' ', $value);

        $normalized = trim(preg_replace('/\s+/', ' ', $cleaned) ?? '');
        $fallback = $normalized !== '' ? $normalized : 'Sentinel Scene';

        return Str::limit($fallback, 120, '');
    }

    private function buildDisplayName(string $base, string $extension): string
    {
        $extension = ltrim($extension, '.');

        return sprintf('%s.%s', $base, $extension);
    }

    private function ensureUniqueFilename(string $directory, string $baseName, string $extension): string
    {
        $disk = Storage::disk('public');
        $cleanDirectory = trim($directory, '/');
        $extension = ltrim($extension, '.');

        $candidateBase = $baseName !== '' ? $baseName : 'sentinel-scene';
        $filename = sprintf('%s.%s', $candidateBase, $extension);
        $counter = 1;

        $pathPrefix = $cleanDirectory === '' ? '' : $cleanDirectory . '/';

        while ($disk->exists($pathPrefix . $filename)) {
            $filename = sprintf('%s-%d.%s', $candidateBase, $counter, $extension);
            $counter++;
        }

        return $filename;
    }
}

