<?php

namespace App\Http\Controllers;

use App\Jobs\ProcessSentinelClipJob;
use App\Jobs\ProcessSentinelSceneJob;
use App\Models\FieldArea;
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

        $requiredCredits = (float) config('app-constants.imagery_processing_cost', 10);
        $currentCredits = $this->creditService->getRemainingCredits($user->id);

        if ($requiredCredits > 0 && $currentCredits < $requiredCredits) {
            return response()->json([
                'success' => false,
                'message' => 'Insufficient credits to process Sentinel imagery.',
                'data' => [
                    'current_credits' => $currentCredits,
                    'required_credits' => $requiredCredits,
                ],
            ], 402);
        }

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

        $deductedCredits = false;

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

            if ($requiredCredits > 0) {
                $deductedCredits = $this->creditService->deductCreditsForProcessing($user->id, $requiredCredits);

                if (!$deductedCredits) {
                    $currentCredits = $this->creditService->getRemainingCredits($user->id);
                    $imagery->delete();

                    return response()->json([
                        'success' => false,
                        'message' => 'Insufficient credits to process Sentinel imagery.',
                        'data' => [
                            'current_credits' => $currentCredits,
                            'required_credits' => $requiredCredits,
                        ],
                    ], 402);
                }
            }

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

            $remainingCredits = $this->creditService->getRemainingCredits($user->id);

            return response()->json([
                'success' => true,
                'message' => 'Sentinel scene queued for processing.',
                'data' => [
                    'id' => $imagery->id,
                    'upload_status' => $imagery->upload_status,
                    'processing_status' => $imagery->processing_status,
                    'current_credits' => $remainingCredits,
                    'required_credits' => $requiredCredits,
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

            if ($deductedCredits && $requiredCredits > 0) {
                $this->creditService->addCreditsToUser($user->id, $requiredCredits, 'SentinelProcessingController');
            }

            return response()->json([
                'success' => false,
                'message' => 'Unable to queue Sentinel imagery processing at this time.',
                'data' => [
                    'current_credits' => $this->creditService->getRemainingCredits($user->id),
                    'required_credits' => $requiredCredits,
                ],
            ], 500);
        }
    }

    public function processClip(Request $request)
    {
        $user = $request->user();

        $validated = $request->validate([
            'field_name' => ['required', 'string', 'max:255'],
            'geometry' => ['required'],
            'area_hectares' => ['required', 'numeric', 'min:0.01'],
            'estimated_credits' => ['nullable', 'numeric', 'min:0'],
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date', 'after_or_equal:date_from'],
            'resolution' => ['nullable', 'integer', 'min:10', 'max:60'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:500'],
            'scene_id' => ['nullable', 'string', 'max:255'],
        ]);

        $geometry = $this->normaliseGeometryPayload($validated['geometry']);
        $areaHectares = (float) $validated['area_hectares'];

        $creditRate = (float) config('app-constants.imagery_credit_cost_per_hectare', 0.0);
        $processingCost = (float) config('app-constants.imagery_processing_cost', 0.0);
        $requiredCredits = max(0.0, ($creditRate * $areaHectares) + $processingCost);

        if (isset($validated['estimated_credits']) && $validated['estimated_credits'] > 0) {
            $requiredCredits = max($requiredCredits, (float) $validated['estimated_credits']);
        }

        $currentCredits = $this->creditService->getRemainingCredits($user->id);

        if ($requiredCredits > 0 && $currentCredits < $requiredCredits) {
            return response()->json([
                'success' => false,
                'message' => 'Insufficient credits to process the clipped Sentinel imagery.',
                'data' => [
                    'current_credits' => $currentCredits,
                    'required_credits' => $requiredCredits,
                ],
            ], 402);
        }

        $displayBase = $this->sanitizeDisplayName($validated['field_name'] . '_' . now()->format('YmdHis'));

        $disk = Storage::disk('public');
        $clipDirectory = 'imagery/clipped';
        $disk->makeDirectory($clipDirectory);

        $outputFilename = $this->ensureUniqueFilename($clipDirectory, $displayBase, 'tif');
        $relativeOutputPath = 'storage/' . trim($clipDirectory, '/') . '/' . $outputFilename;

        $deductedCredits = false;
        $fieldArea = null;
        $imagery = null;

        try {
            if ($requiredCredits > 0) {
                $deductedCredits = $this->creditService->deductCreditsForProcessing($user->id, $requiredCredits, 'SentinelClip');

                if (!$deductedCredits) {
                    $currentCredits = $this->creditService->getRemainingCredits($user->id);

                    return response()->json([
                        'success' => false,
                        'message' => 'Insufficient credits to process the clipped Sentinel imagery.',
                        'data' => [
                            'current_credits' => $currentCredits,
                            'required_credits' => $requiredCredits,
                        ],
                    ], 402);
                }
            }

            $fieldArea = FieldArea::create([
                'user_id' => $user->id,
                'name' => $validated['field_name'],
                'geom' => $geometry,
                'area_ha' => $areaHectares,
            ]);

            $imagery = ImageryData::create([
                'user_id' => $user->id,
                'source_type' => 'sentinel-clip',
                'original_name' => $outputFilename,
                'stored_name' => $outputFilename,
                'size' => 0,
                'format' => 'tif',
                'path' => $relativeOutputPath,
                'upload_status' => 'uploading',
                'processing_status' => 'waiting',
                'uploaded_at' => now(),
            ]);

            $payload = [
                'geometry' => $geometry,
                'output_filename' => $outputFilename,
                'field_name' => $validated['field_name'],
                'field_area_hectares' => $areaHectares,
                'credit_charge' => $requiredCredits,
                'date_from' => $validated['date_from'] ?? now()->subDays(30)->toDateString(),
                'date_to' => $validated['date_to'] ?? now()->toDateString(),
                'resolution' => $validated['resolution'] ?? null,
                'limit' => $validated['limit'] ?? null,
                'scene_id' => $validated['scene_id'] ?? null,
            ];

            ProcessSentinelClipJob::dispatch(
                $imagery->id,
                $fieldArea->id,
                array_filter($payload, fn ($value) => $value !== null)
            )->onQueue('processing');

            $remainingCredits = $this->creditService->getRemainingCredits($user->id);

            return response()->json([
                'success' => true,
                'message' => 'Sentinel-2 clip queued for processing.',
                'data' => [
                    'imagery_id' => $imagery->id,
                    'field_area_id' => $fieldArea->id,
                    'current_credits' => $remainingCredits,
                    'required_credits' => $requiredCredits,
                ],
            ], 202);
        } catch (Throwable $exception) {
            Log::error('SentinelProcessingController@processClip failed to queue processing.', [
                'error' => $exception->getMessage(),
                'trace' => $exception->getTraceAsString(),
            ]);

            if ($imagery) {
                $imagery->delete();
            }

            if ($fieldArea) {
                $fieldArea->delete();
            }

            if ($deductedCredits && $requiredCredits > 0) {
                $this->creditService->addCreditsToUser($user->id, $requiredCredits, 'SentinelClipRollback');
            }

            return response()->json([
                'success' => false,
                'message' => 'Unable to queue Sentinel clip processing at this time.',
                'data' => [
                    'current_credits' => $this->creditService->getRemainingCredits($user->id),
                    'required_credits' => $requiredCredits,
                ],
            ], 500);
        }
    }

    private function normaliseGeometryPayload($geometry): array
    {
        if (is_string($geometry)) {
            try {
                $geometry = json_decode($geometry, true, 512, JSON_THROW_ON_ERROR);
            } catch (\JsonException $exception) {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    'geometry' => 'Invalid GeoJSON payload supplied.',
                ]);
            }
        }

        if (!is_array($geometry)) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'geometry' => 'Invalid GeoJSON payload supplied.',
            ]);
        }

        $type = $geometry['type'] ?? null;

        if ($type === 'FeatureCollection') {
            return $geometry;
        }

        if ($type === 'Feature') {
            return [
                'type' => 'FeatureCollection',
                'features' => [
                    $geometry,
                ],
            ];
        }

        if (isset($geometry['features']) && is_array($geometry['features'])) {
            return [
                'type' => 'FeatureCollection',
                'features' => $geometry['features'],
            ];
        }

        if (isset($geometry['geometry']) && is_array($geometry['geometry'])) {
            return [
                'type' => 'FeatureCollection',
                'features' => [[
                    'type' => 'Feature',
                    'properties' => $geometry['properties'] ?? [],
                    'geometry' => $geometry['geometry'],
                ]],
            ];
        }

        throw \Illuminate\Validation\ValidationException::withMessages([
            'geometry' => 'Invalid GeoJSON payload supplied.',
        ]);
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
