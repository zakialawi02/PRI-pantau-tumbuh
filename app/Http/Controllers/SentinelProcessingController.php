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
            'geojson' => ['required'],
            'geometry' => ['nullable'],
            'area_hectares' => ['required', 'numeric', 'min:0.01'],
            'estimated_credits' => ['nullable', 'numeric', 'min:0'],
        ]);

        $geojsonPayload = $validated['geojson'];
        if (is_string($geojsonPayload)) {
            $decoded = json_decode($geojsonPayload, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid GeoJSON payload.',
                ], 422);
            }
            $geojsonPayload = $decoded;
        }

        if (is_array($geojsonPayload) && ($geojsonPayload['type'] ?? '') === 'Feature') {
            $geojsonPayload = [
                'type' => 'FeatureCollection',
                'features' => [$geojsonPayload],
            ];
        }

        if (!is_array($geojsonPayload) || ($geojsonPayload['type'] ?? '') !== 'FeatureCollection' || empty($geojsonPayload['features'])) {
            return response()->json([
                'success' => false,
                'message' => 'GeoJSON payload must be a FeatureCollection with at least one feature.',
            ], 422);
        }

        $feature = $geojsonPayload['features'][0] ?? null;
        if (!is_array($feature) || empty($feature['geometry']) || !is_array($feature['geometry'])) {
            return response()->json([
                'success' => false,
                'message' => 'The provided GeoJSON feature is missing geometry data.',
            ], 422);
        }

        if (!isset($feature['properties']) || !is_array($feature['properties'])) {
            $feature['properties'] = [];
        }
        $geojsonPayload['features'][0] = $feature;

        $geometry = $validated['geometry'] ?? $feature['geometry'];
        if (!is_array($geometry)) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid geometry provided.',
            ], 422);
        }

        $areaHa = (float) $validated['area_hectares'];
        $creditRate = (float) config('app-constants.imagery_credit_cost_per_hectare', 0);
        $requiredCredits = round($areaHa * $creditRate, 2);
        if ($requiredCredits < 0) {
            $requiredCredits = 0;
        }

        $currentCredits = $this->creditService->getRemainingCredits($user->id);
        if ($requiredCredits > 0 && $currentCredits < $requiredCredits) {
            return response()->json([
                'success' => false,
                'message' => 'Insufficient credits to process clipped Sentinel imagery.',
                'data' => [
                    'current_credits' => $currentCredits,
                    'required_credits' => $requiredCredits,
                ],
            ], 402);
        }

        $rawFieldName = trim($validated['field_name']);
        $displayBase = $this->sanitizeDisplayName(($rawFieldName !== '' ? $rawFieldName : 'SentinelClip') . '_' . now()->format('YmdHis'));
        $outputFilename = $this->ensureUniqueFilename('imagery/clipped', $displayBase, 'tif');
        $storedName = 'clipped/' . $outputFilename;
        $originalName = ($rawFieldName !== '' ? $rawFieldName : pathinfo($outputFilename, PATHINFO_FILENAME)) . '.tif';

        $fieldArea = null;
        $imagery = null;
        $deductedCredits = false;

        try {
            $fieldArea = FieldArea::create([
                'user_id' => $user->id,
                'name' => $rawFieldName !== '' ? $rawFieldName : $displayBase,
                'area_ha' => $areaHa,
                'geom' => $geojsonPayload,
            ]);

            $imagery = ImageryData::create([
                'user_id' => $user->id,
                'source_type' => 'sentinel-2',
                'original_name' => $originalName,
                'stored_name' => $storedName,
                'size' => 0,
                'format' => 'tif',
                'path' => 'storage/imagery/' . $storedName,
                'upload_status' => 'pending',
                'processing_status' => 'waiting',
                'uploaded_at' => now(),
            ]);

            if ($requiredCredits > 0) {
                $deductedCredits = $this->creditService->deductCreditsForProcessing($user->id, $requiredCredits, 'SentinelClip');

                if (!$deductedCredits) {
                    $currentCredits = $this->creditService->getRemainingCredits($user->id);
                    $imagery->delete();
                    $fieldArea->delete();

                    return response()->json([
                        'success' => false,
                        'message' => 'Insufficient credits to process clipped Sentinel imagery.',
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
                    'geometry' => $geometry,
                    'geojson' => $geojsonPayload,
                    'output_filename' => $outputFilename,
                    'field_name' => $rawFieldName,
                    'area_hectares' => $areaHa,
                    'required_credits' => $requiredCredits,
                ]
            )->onQueue('processing');

            $remainingCredits = $this->creditService->getRemainingCredits($user->id);

            return response()->json([
                'success' => true,
                'message' => 'Sentinel clip queued for processing.',
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
                $this->creditService->addCreditsToUser($user->id, $requiredCredits, 'SentinelClipController');
            }

            return response()->json([
                'success' => false,
                'message' => 'Unable to queue clipped Sentinel imagery at this time.',
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
