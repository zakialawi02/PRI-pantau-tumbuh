<?php

namespace App\Http\Controllers;

use App\Jobs\ProcessSentinelClipJob;
use App\Jobs\ProcessSentinelSceneJob;
use App\Models\ImageryData;
use App\Models\FieldArea;
use App\Services\CreditService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
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
            'geometry' => ['required', 'array'],
            'geometry.type' => ['required', 'string'],
            'area_square_meters' => ['required', 'numeric', 'min:1'],
            'area_hectares' => ['nullable', 'numeric', 'min:0'],
            'credit_cost' => ['nullable', 'numeric', 'min:0'],
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date'],
        ]);

        $fieldName = trim($validated['field_name'] ?? '') ?: null;
        $geometryPayload = $validated['geometry'];

        try {
            $featureCollection = $this->ensureFeatureCollection($geometryPayload);
        } catch (\InvalidArgumentException $exception) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid geometry provided for clipping.',
            ], 422);
        }

        $areaSquareMeters = (float) $validated['area_square_meters'];
        $areaHectares = $areaSquareMeters / 10000;

        $baseProcessingCost = (float) config('app-constants.imagery_processing_cost', 0);
        $perHectareRate = (float) config('app-constants.imagery_credit_cost_per_hectare', 0);

        $areaCreditCost = $areaHectares * $perHectareRate;
        $requiredCredits = round($baseProcessingCost + $areaCreditCost, 2);
        if ($baseProcessingCost > 0 && $requiredCredits < $baseProcessingCost) {
            $requiredCredits = round($baseProcessingCost, 2);
        }

        $currentCredits = $this->creditService->getRemainingCredits($user->id);
        if ($requiredCredits > 0 && $currentCredits < $requiredCredits) {
            return response()->json([
                'success' => false,
                'message' => 'Insufficient credits to process Sentinel clipping.',
                'data' => [
                    'current_credits' => $currentCredits,
                    'required_credits' => $requiredCredits,
                ],
            ], 402);
        }

        $deductedCredits = false;
        $fieldArea = null;
        $imagery = null;

        try {
            if ($requiredCredits > 0) {
                $deductedCredits = $this->creditService->deductCreditsForProcessing(
                    $user->id,
                    $requiredCredits,
                    'SentinelProcessingController@processClip'
                );

                if (!$deductedCredits) {
                    $currentCredits = $this->creditService->getRemainingCredits($user->id);

                    return response()->json([
                        'success' => false,
                        'message' => 'Insufficient credits to process Sentinel clipping.',
                        'data' => [
                            'current_credits' => $currentCredits,
                            'required_credits' => $requiredCredits,
                        ],
                    ], 402);
                }
            } else {
                $deductedCredits = true;
            }

            DB::beginTransaction();

            $fieldAreaName = $fieldName ?? ('Sentinel Clip ' . now()->format('Y-m-d'));

            $fieldArea = FieldArea::create([
                'user_id' => $user->id,
                'name' => $fieldAreaName,
                'area_ha' => round($areaHectares, 4),
                'geom' => $featureCollection,
            ]);

            $displayBase = $fieldName ?: 'SentinelClip';
            $sanitizedBase = $this->sanitizeDisplayName(
                $displayBase . '_' . now()->format('Ymd_His')
            );
            $outputFilename = $this->ensureUniqueFilename(
                'imagery/clipped',
                $sanitizedBase,
                'tif'
            );
            $originalName = $displayBase . '.tif';

            $imagery = ImageryData::create([
                'user_id' => $user->id,
                'source_type' => 'sentinel-2-clip',
                'original_name' => $originalName,
                'stored_name' => $outputFilename,
                'size' => 0,
                'format' => 'tif',
                'path' => 'storage/imagery/clipped/' . $outputFilename,
                'upload_status' => 'processing',
                'processing_status' => 'waiting',
                'uploaded_at' => now(),
            ]);

            DB::commit();

            $payload = [
                'geometry' => $featureCollection,
                'field_name' => $fieldName,
                'area_square_meters' => $areaSquareMeters,
                'area_hectares' => $areaHectares,
                'credit_cost' => $requiredCredits,
                'output_filename' => $outputFilename,
            ];

            if (!empty($validated['date_from'])) {
                $payload['date_from'] = $validated['date_from'];
            }

            if (!empty($validated['date_to'])) {
                $payload['date_to'] = $validated['date_to'];
            }

            ProcessSentinelClipJob::dispatch(
                $imagery->id,
                $fieldArea->id,
                $payload
            )->onQueue('processing');

            $remainingCredits = $this->creditService->getRemainingCredits($user->id);

            return response()->json([
                'success' => true,
                'message' => 'Sentinel clipping request has been queued for processing.',
                'data' => [
                    'imagery_id' => $imagery->id,
                    'field_area_id' => $fieldArea->id,
                    'current_credits' => $remainingCredits,
                    'required_credits' => $requiredCredits,
                ],
            ], 202);
        } catch (Throwable $exception) {
            if (DB::transactionLevel() > 0) {
                DB::rollBack();
            }

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
                $this->creditService->addCreditsToUser(
                    $user->id,
                    $requiredCredits,
                    'SentinelProcessingController@processClip'
                );
            }

            return response()->json([
                'success' => false,
                'message' => 'Unable to queue Sentinel clipping at this time.',
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

    private function ensureFeatureCollection(array $geometry): array
    {
        if (is_string($geometry)) {
            $decoded = json_decode($geometry, true);

            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new \InvalidArgumentException('Geometry payload is not valid JSON.');
            }

            $geometry = $decoded;
        }

        if (!is_array($geometry)) {
            throw new \InvalidArgumentException('Geometry payload must be an array.');
        }

        $type = $geometry['type'] ?? null;

        if (!is_string($type) || $type === '') {
            throw new \InvalidArgumentException('Geometry type is missing.');
        }

        if ($type === 'FeatureCollection') {
            if (!isset($geometry['features']) || !is_array($geometry['features']) || empty($geometry['features'])) {
                throw new \InvalidArgumentException('Feature collection is missing features.');
            }

            $features = [];

            foreach ($geometry['features'] as $feature) {
                if (!is_array($feature)) {
                    throw new \InvalidArgumentException('Feature collection contains an invalid feature.');
                }

                $features[] = $this->normalizeFeature($feature);
            }

            $geometry['features'] = $features;

            return $geometry;
        }

        if ($type === 'Feature') {
            if (!isset($geometry['geometry']) || !is_array($geometry['geometry'])) {
                throw new \InvalidArgumentException('Feature geometry is missing.');
            }

            return [
                'type' => 'FeatureCollection',
                'features' => [
                    $this->normalizeFeature($geometry),
                ],
            ];
        }

        if (!isset($geometry['coordinates'])) {
            throw new \InvalidArgumentException('Geometry coordinates missing.');
        }

        return [
            'type' => 'FeatureCollection',
            'features' => [[
                'type' => 'Feature',
                'properties' => [],
                'geometry' => $this->normalizeGeometry($geometry),
            ]],
        ];
    }

    private function normalizeFeature(array $feature): array
    {
        $featureType = $feature['type'] ?? 'Feature';
        if ($featureType !== 'Feature') {
            throw new \InvalidArgumentException('Invalid feature type provided.');
        }

        if (!isset($feature['geometry']) || !is_array($feature['geometry'])) {
            throw new \InvalidArgumentException('Feature geometry is missing.');
        }

        $normalized = [
            'type' => 'Feature',
            'properties' => isset($feature['properties']) && is_array($feature['properties'])
                ? $feature['properties']
                : [],
            'geometry' => $this->normalizeGeometry($feature['geometry']),
        ];

        if (isset($feature['id'])) {
            $normalized['id'] = $feature['id'];
        }

        return $normalized;
    }

    private function normalizeGeometry(array $geometry): array
    {
        $geomType = $geometry['type'] ?? null;

        if (!is_string($geomType) || $geomType === '') {
            throw new \InvalidArgumentException('Geometry type is missing.');
        }

        if (!isset($geometry['coordinates']) || !is_array($geometry['coordinates'])) {
            throw new \InvalidArgumentException('Geometry coordinates missing.');
        }

        return [
            'type' => $geomType,
            'coordinates' => $geometry['coordinates'],
        ];
    }
}
