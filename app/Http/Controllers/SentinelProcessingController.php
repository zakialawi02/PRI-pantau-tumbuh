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
use Illuminate\Validation\Rule;
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
            'geometry.coordinates' => ['required', 'array'],
            'area_hectares' => ['required', 'numeric', 'min:0.01'],
            'mode' => ['required', Rule::in(['auto', 'manual'])],
            'filters' => ['required', 'array'],
            'filters.start_date' => ['required', 'date'],
            'filters.end_date' => ['required', 'date', 'after_or_equal:filters.start_date'],
            'filters.cloud_cover' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'filters.product_level' => ['nullable', 'string', 'max:32'],
            'selected_scene' => ['nullable', 'array'],
            'selected_scene.id' => ['nullable', 'string'],
            'selected_scene.product_id' => ['nullable', 'string'],
            'selected_scene.title' => ['nullable', 'string'],
            'selected_scene.acquisition_date' => ['nullable', 'string'],
            'selected_scene.cloud_cover' => ['nullable', 'numeric'],
            'selected_scene.collection' => ['nullable', 'string'],
            'selected_scene.processing_level' => ['nullable', 'string'],
        ]);

        if ($validated['mode'] === 'manual' && empty($validated['selected_scene'])) {
            return response()->json([
                'success' => false,
                'message' => 'Selected scene details are required for manual mode.',
            ], 422);
        }

        $areaHectares = (float) $validated['area_hectares'];
        $creditCostPerHectare = config('app-constants.imagery_credit_cost_per_hectare', 1);
        $creditCost = round($areaHectares * $creditCostPerHectare, 2);

        if ($creditCost <= 0) {
            return response()->json([
                'success' => false,
                'message' => 'Unable to determine credit cost for the requested area.',
            ], 422);
        }

        if (!$this->creditService->deductCreditsForProcessing($user->id, $creditCost, 'SentinelClip')) {
            return response()->json([
                'success' => false,
                'message' => 'Insufficient credit points to process clipped imagery.',
                'current_credits' => $this->creditService->getRemainingCredits($user->id),
            ], 422);
        }

        $disk = Storage::disk('public');
        $imageryDirectory = 'imagery';
        $sentinelDirectory = 'imagery/download/sentinel-clip';
        $disk->makeDirectory($imageryDirectory);
        $disk->makeDirectory($sentinelDirectory);

        $selectedScene = $validated['selected_scene'] ?? [];
        $sceneTitle = trim($selectedScene['title'] ?? '') ?: 'sentinel_clip';
        $displayTitle = $this->sanitizeDisplayName($sceneTitle . '_' . now()->format('YmdHis'));
        $outputFilename = $this->ensureUniqueFilename($imageryDirectory, $displayTitle . '_clip', 'tif');

        try {
            $imagery = ImageryData::create([
                'user_id' => $user->id,
                'source_type' => 'sentinel-clip',
                'original_name' => $displayTitle . '.tif',
                'stored_name' => $outputFilename,
                'size' => 0,
                'format' => 'tif',
                'path' => 'storage/' . trim($imageryDirectory, '/') . '/' . $outputFilename,
                'upload_status' => 'uploading',
                'processing_status' => 'waiting',
                'uploaded_at' => now(),
            ]);

            $jobOptions = [
                'geometry' => $validated['geometry'],
                'filters' => $validated['filters'],
                'selected_scene' => $selectedScene,
                'mode' => $validated['mode'],
                'output_filename' => $outputFilename,
                'display_name' => $displayTitle,
                'geometry_properties' => [
                    'area_hectares' => $areaHectares,
                ],
            ];

            ProcessSentinelClipJob::dispatch($imagery->id, $jobOptions, $creditCost)->onQueue('download');

            return response()->json([
                'success' => true,
                'message' => 'Clipped Sentinel imagery queued for processing.',
                'data' => [
                    'id' => $imagery->id,
                    'upload_status' => $imagery->upload_status,
                    'processing_status' => $imagery->processing_status,
                    'current_credits' => $this->creditService->getRemainingCredits($user->id),
                ],
            ], 202);
        } catch (Throwable $exception) {
            Log::error('SentinelProcessingController@processClip failed.', [
                'error' => $exception->getMessage(),
                'trace' => $exception->getTraceAsString(),
            ]);

            if (isset($imagery)) {
                $imagery->update([
                    'upload_status' => 'failed',
                    'processing_status' => 'error',
                ]);
            }

            $this->creditService->addCreditsToUser($user->id, $creditCost, 'SentinelClip');

            return response()->json([
                'success' => false,
                'message' => 'Unable to queue clipped Sentinel imagery processing at this time.',
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
