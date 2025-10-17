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
            'field_name' => ['nullable', 'string', 'max:255'],
            'feature' => ['required'],
            'area_hectares' => ['required', 'numeric', 'min:0.0001'],
            'credit_cost' => ['nullable', 'numeric', 'min:0'],
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date', 'after_or_equal:date_from'],
            'resolution' => ['nullable', 'integer', 'min:10', 'max:60'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:200'],
            'scene_id' => ['nullable', 'string', 'max:255'],
        ]);

        $feature = $validated['feature'];
        if (is_string($feature)) {
            try {
                $feature = json_decode($feature, true, 512, JSON_THROW_ON_ERROR);
            } catch (\JsonException $exception) {
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid GeoJSON payload provided.',
                ], 422);
            }
        }

        if (!is_array($feature) || !isset($feature['type']) || strtolower($feature['type']) !== 'feature' || !isset($feature['geometry'])) {
            return response()->json([
                'success' => false,
                'message' => 'A valid GeoJSON feature is required to process Sentinel imagery.',
            ], 422);
        }

        $geometry = $feature['geometry'];
        if (!is_array($geometry) || empty($geometry)) {
            return response()->json([
                'success' => false,
                'message' => 'GeoJSON geometry is missing or malformed.',
            ], 422);
        }

        $feature['properties'] = $feature['properties'] ?? [];
        $featureCollection = [
            'type' => 'FeatureCollection',
            'features' => [
                [
                    'type' => 'Feature',
                    'properties' => $feature['properties'],
                    'geometry' => $geometry,
                ],
            ],
        ];

        $areaHa = (float) $validated['area_hectares'];
        $creditRate = (float) config('app-constants.imagery_credit_cost_per_hectare', 0);
        $requiredCredits = (array_key_exists('credit_cost', $validated) && $validated['credit_cost'] !== null)
            ? (float) $validated['credit_cost']
            : round($areaHa * $creditRate, 2);
        $requiredCredits = max($requiredCredits, 0);

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

        $disk = Storage::disk('public');
        $imageryDirectory = 'imagery';
        $disk->makeDirectory($imageryDirectory);

        $fieldName = trim($validated['field_name'] ?? '') ?: 'Sentinel Clip ' . now()->format('Ymd His');
        $safeBaseName = $this->sanitizeDisplayName($fieldName . '_' . now()->format('YmdHis'));
        $outputFilename = $this->ensureUniqueFilename($imageryDirectory, $safeBaseName, 'tif');
        $originalName = Str::limit(trim($fieldName), 120, '') ?: 'Sentinel Clip';
        $originalDisplayName = $originalName . '.tif';

        $deductedCredits = false;

        try {
            $fieldArea = FieldArea::create([
                'user_id' => $user->id,
                'name' => $fieldName,
                'geom' => $featureCollection,
                'area_ha' => $areaHa,
            ]);

            $imagery = ImageryData::create([
                'user_id' => $user->id,
                'source_type' => 'sentinel-2-clip',
                'original_name' => $originalDisplayName,
                'stored_name' => $outputFilename,
                'size' => 0,
                'format' => pathinfo($outputFilename, PATHINFO_EXTENSION) ?: 'tif',
                'path' => 'storage/' . trim($imageryDirectory, '/') . '/' . $outputFilename,
                'upload_status' => 'pending',
                'processing_status' => 'waiting',
                'uploaded_at' => now(),
            ]);

            if ($requiredCredits > 0) {
                $deductedCredits = $this->creditService->deductCreditsForProcessing((string) $user->id, $requiredCredits, 'SentinelClipController');
                if (!$deductedCredits) {
                    $currentCredits = $this->creditService->getRemainingCredits($user->id);
                    $imagery->delete();
                    $fieldArea->delete();

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

            ProcessSentinelClipJob::dispatch(
                $imagery->id,
                $fieldArea->id,
                [
                    'geojson' => $featureCollection,
                    'geometry' => $geometry,
                    'field_name' => $fieldName,
                    'area_hectares' => $areaHa,
                    'credit_cost' => $requiredCredits,
                    'output_filename' => $outputFilename,
                    'scene_id' => $validated['scene_id'] ?? null,
                    'date_from' => $validated['date_from'] ?? null,
                    'date_to' => $validated['date_to'] ?? null,
                    'resolution' => $validated['resolution'] ?? null,
                    'limit' => $validated['limit'] ?? null,
                ]
            )->onQueue('processing');

            $remainingCredits = $this->creditService->getRemainingCredits($user->id);

            return response()->json([
                'success' => true,
                'message' => 'Sentinel clipped imagery queued for processing.',
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

            if (isset($imagery)) {
                $imagery->delete();
            }

            if (isset($fieldArea)) {
                $fieldArea->delete();
            }

            if ($deductedCredits && $requiredCredits > 0) {
                $this->creditService->addCreditsToUser((string) $user->id, $requiredCredits, 'SentinelClipController');
            }

            return response()->json([
                'success' => false,
                'message' => 'Unable to queue Sentinel clipped imagery at this time.',
                'data' => [
                    'current_credits' => $this->creditService->getRemainingCredits($user->id),
                    'required_credits' => $requiredCredits,
                ],
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
