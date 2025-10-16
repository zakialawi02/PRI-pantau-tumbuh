<?php

namespace App\Http\Controllers;

use App\Jobs\ProcessSentinelClipJob;
use App\Jobs\ProcessSentinelSceneJob;
use App\Models\ImageryData;
use App\Services\CreditService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

class SentinelProcessingController extends Controller
{
    protected $creditService;

    public function __construct(CreditService $creditService)
    {
        $this->creditService = $creditService;
    }

    public function processScene(Request $request)
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

        $rawTitle = trim($validated['title'] ?? '') ?: now()->format('YmdHis') . '_Sentinel_Scene';
        $displayTitle = $this->sanitizeDisplayName($rawTitle . '_' . now()->format('YmdHis'));
        $finalDisplayName = $displayTitle . '.tif';

        $disk = Storage::disk('public');
        $imageryDirectory = 'imagery';
        $sentinelDirectory = 'imagery/download/sentinel';

        $disk->makeDirectory($imageryDirectory);
        $disk->makeDirectory($sentinelDirectory);
        $zipFilename = $this->ensureUniqueFilename($sentinelDirectory, $displayTitle, 'zip');
        $outputBase = Str::limit($displayTitle . '_multispectral', 160, '');
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
            )->onQueue('download');

            $this->creditService->deductCreditsForProcessing($user->id, config('app-constants.imagery_processing_cost', 10));

            return response()->json([
                'success' => true,
                'message' => 'Sentinel scene queued for processing.',
                'data' => [
                    'id' => $imagery->id,
                    'upload_status' => $imagery->upload_status,
                    'processing_status' => $imagery->processing_status,
                    'current_credits' => $this->creditService->getRemainingCredits($user->id),
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
                'current_credits' => $this->creditService->getRemainingCredits($user->id),
            ], 500);
        }
    }

    public function processClip(Request $request)
    {
        $user = $request->user();

        $validated = $request->validate([
            'geometry' => ['required', 'array'],
            'geometry.type' => ['required', 'string'],
            'geometry.coordinates' => ['required'],
            'area_hectares' => ['required', 'numeric', 'min:0.01'],
            'selection_mode' => ['required', 'in:auto,manual'],
            'scene.id' => ['required', 'string', 'max:255'],
            'scene.datetime' => ['nullable', 'string', 'max:255'],
            'scene.product_level' => ['nullable', 'string', 'max:255'],
            'scene.cloud_cover' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'scene.collection' => ['nullable', 'string', 'max:255'],
            'scene.title' => ['nullable', 'string', 'max:255'],
            'scene.download_url' => ['nullable', 'url'],
            'filters.max_cloud' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'filters.resolution' => ['nullable', 'numeric', 'min:5', 'max:60'],
            'filters.product_level' => ['nullable', 'string', 'max:255'],
            'filters.start_date' => ['nullable', 'string', 'max:50'],
            'filters.end_date' => ['nullable', 'string', 'max:50'],
            'filters.latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'filters.longitude' => ['nullable', 'numeric', 'between:-180,180'],
        ]);

        $geometry = $validated['geometry'];
        $areaHectares = (float) $validated['area_hectares'];
        $scene = $validated['scene'];
        $filters = $validated['filters'] ?? [];

        $creditRate = config('app-constants.imagery_credit_cost_per_hectare', 1.0);
        $creditCost = round($areaHectares * $creditRate, 2);

        if ($creditCost <= 0) {
            return response()->json([
                'success' => false,
                'message' => 'Unable to calculate credit usage for the selected area.',
            ], 422);
        }

        $productLevel = strtoupper($scene['product_level'] ?? 'S2MSI2A');
        $cloudLimit = isset($filters['max_cloud']) ? (float) $filters['max_cloud'] : (float) ($scene['cloud_cover'] ?? 60);
        $resolution = isset($filters['resolution']) ? (float) $filters['resolution'] : (float) config('app-constants.default_sentinel_resolution', 10);

        if (!$this->creditService->deductCreditsForProcessing($user->id, $creditCost, 'ClipSentinel')) {
            return response()->json([
                'success' => false,
                'message' => 'Insufficient credits to process clipped Sentinel imagery.',
                'current_credits' => $this->creditService->getRemainingCredits($user->id),
            ], 422);
        }

        $disk = Storage::disk('public');
        $imageryDirectory = 'imagery';
        $disk->makeDirectory($imageryDirectory);

        $rawTitle = trim($scene['title'] ?? '') ?: ($scene['id'] ?? 'SentinelClip');
        $displayTitle = $this->sanitizeDisplayName($rawTitle . '_' . now()->format('YmdHis'));
        $outputFilename = $this->ensureUniqueFilename($imageryDirectory, $displayTitle . '_clip', 'tif');

        try {
            $imagery = ImageryData::create([
                'user_id' => $user->id,
                'source_type' => 'sentinel-2-clip',
                'original_name' => $displayTitle . '.tif',
                'stored_name' => $outputFilename,
                'size' => 0,
                'format' => 'tif',
                'path' => 'storage/' . trim($imageryDirectory, '/') . '/' . $outputFilename,
                'upload_status' => 'uploading',
                'processing_status' => 'waiting',
                'uploaded_at' => now(),
            ]);

            ProcessSentinelClipJob::dispatch(
                $imagery->id,
                $geometry,
                $scene['id'],
                $scene['datetime'] ?? null,
                $productLevel,
                $cloudLimit,
                $resolution,
                [
                    'selection_mode' => $validated['selection_mode'],
                    'scene' => $scene,
                    'filters' => $filters,
                ]
            )->onQueue('download');

            return response()->json([
                'success' => true,
                'message' => 'Sentinel clip job queued for processing.',
                'data' => [
                    'id' => $imagery->id,
                    'upload_status' => $imagery->upload_status,
                    'processing_status' => $imagery->processing_status,
                    'current_credits' => $this->creditService->getRemainingCredits($user->id),
                    'credit_cost' => $creditCost,
                ],
            ], 202);
        } catch (Throwable $exception) {
            Log::error('SentinelProcessingController@processClip failed to queue processing.', [
                'error' => $exception->getMessage(),
                'trace' => $exception->getTraceAsString(),
            ]);

            $this->creditService->addCreditsToUser($user->id, $creditCost, 'ClipSentinelRollback');

            return response()->json([
                'success' => false,
                'message' => 'Unable to queue Sentinel clipping at this time.',
                'current_credits' => $this->creditService->getRemainingCredits($user->id),
            ], 500);
        }
    }

    private function sanitizeDisplayName(string $value): string
    {
        $cleaned = str_replace([
            ' ',
            '\\',
            '/',
            ':',
            "\"",
            '*',
            '?',
            '<',
            '>',
            '|',
            'SAFE',
            '.'
        ], ' ', $value);

        $normalized = trim(preg_replace('/\s+/', '', $cleaned) ?? '');
        $fallback = $normalized !== '' ? $normalized : 'Sentinel_Scene';

        return Str::limit($fallback, 120, '');
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
