<?php

namespace App\Http\Controllers;

use App\Jobs\ProcessSentinelClipJob;
use App\Jobs\ProcessSentinelSceneJob;
use App\Models\ImageryData;
use App\Services\CreditService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Carbon;
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
            'geometry' => ['required'],
            'area_hectares' => ['required', 'numeric', 'min:0.0001'],
            'start_date' => ['required', 'date'],
            'field_name' => ['nullable', 'string', 'max:255'],
        ]);

        $geometryPayload = $validated['geometry'];

        try {
            if (is_string($geometryPayload)) {
                $geometryPayload = json_decode($geometryPayload, true, 512, JSON_THROW_ON_ERROR);
            }
        } catch (\JsonException $exception) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid GeoJSON payload provided.',
            ], 422);
        }

        if (!is_array($geometryPayload)) {
            return response()->json([
                'success' => false,
                'message' => 'GeoJSON geometry must be an object.',
            ], 422);
        }

        $featureCollection = $this->normalizeClipGeometry($geometryPayload);

        if (empty($featureCollection['features'])) {
            return response()->json([
                'success' => false,
                'message' => 'GeoJSON payload must include at least one polygon feature.',
            ], 422);
        }

        $areaHectares = max((float) $validated['area_hectares'], 0);
        if ($areaHectares <= 0) {
            return response()->json([
                'success' => false,
                'message' => 'The clipped area must be greater than zero hectares.',
            ], 422);
        }

        $creditRate = (float) config('app-constants.imagery_credit_cost_per_hectare', 0);
        $processingCost = (float) config('app-constants.imagery_processing_cost', 0);
        $areaCredits = round($areaHectares * $creditRate, 2);
        $totalCredits = round($areaCredits + $processingCost, 2);

        $currentCredits = $this->creditService->getRemainingCredits($user->id);
        if ($totalCredits > 0 && $currentCredits < $totalCredits) {
            return response()->json([
                'success' => false,
                'message' => 'Insufficient credits to process Sentinel-2 clip imagery.',
                'data' => [
                    'current_credits' => $currentCredits,
                    'required_credits' => $totalCredits,
                ],
            ], 402);
        }

        $disk = Storage::disk('public');
        $imageryDirectory = 'imagery';
        $clipDirectory = 'imagery/clipped';
        $disk->makeDirectory($imageryDirectory);
        $disk->makeDirectory($clipDirectory);

        $rawName = trim($validated['field_name'] ?? '') ?: 'SentinelClip';
        $baseName = $this->sanitizeDisplayName($rawName . '_' . now()->format('YmdHis'));
        $storedFilename = $this->ensureUniqueFilename($clipDirectory, $baseName, 'tif');
        $displayName = $baseName . '.tif';

        $outputRelativePath = 'storage/' . trim($clipDirectory, '/') . '/' . $storedFilename;

        $deductedCredits = false;

        try {
            if ($totalCredits > 0) {
                $deductedCredits = $this->creditService->deductCreditsForProcessing($user->id, $totalCredits, 'SentinelClip');

                if (!$deductedCredits) {
                    $currentCredits = $this->creditService->getRemainingCredits($user->id);

                    return response()->json([
                        'success' => false,
                        'message' => 'Insufficient credits to process Sentinel-2 clip imagery.',
                        'data' => [
                            'current_credits' => $currentCredits,
                            'required_credits' => $totalCredits,
                        ],
                    ], 402);
                }
            }

            $imagery = ImageryData::create([
                'user_id' => $user->id,
                'source_type' => 'sentinel-2-clip',
                'original_name' => $displayName,
                'stored_name' => $storedFilename,
                'size' => 0,
                'format' => 'tif',
                'path' => $outputRelativePath,
                'upload_status' => 'uploading',
                'processing_status' => 'waiting',
                'uploaded_at' => now(),
            ]);

            $startDate = Carbon::parse($validated['start_date'])->startOfDay()->toDateString();

            ProcessSentinelClipJob::dispatch(
                $imagery->id,
                $request->input('field_area_id'),
                [
                    'geometry' => $featureCollection,
                    'output_filename' => $storedFilename,
                    'display_name' => $displayName,
                    'area_hectares' => $areaHectares,
                    'start_date' => $startDate,
                    'max_records' => 10,
                    'credit_cost' => $totalCredits,
                    'deducted_credits' => $totalCredits,
                    'estimated_area_credits' => $areaCredits,
                    'field_name' => $validated['field_name'] ?? null,
                ]
            )->onQueue('download');

            $remainingCredits = $this->creditService->getRemainingCredits($user->id);

            return response()->json([
                'success' => true,
                'message' => 'Sentinel-2 clip queued for processing.',
                'data' => [
                    'id' => $imagery->id,
                    'upload_status' => $imagery->upload_status,
                    'processing_status' => $imagery->processing_status,
                    'clip_area_hectares' => $areaHectares,
                    'estimated_credits' => $totalCredits,
                    'current_credits' => $remainingCredits,
                    'required_credits' => $totalCredits,
                ],
            ], 202);
        } catch (Throwable $exception) {
            Log::error('SentinelProcessingController@processClip failed to queue processing.', [
                'error' => $exception->getMessage(),
                'trace' => $exception->getTraceAsString(),
            ]);

            if (isset($imagery)) {
                $imagery->update([
                    'upload_status' => 'failed',
                    'processing_status' => 'error',
                ]);
            }

            if ($deductedCredits && $totalCredits > 0) {
                $this->creditService->addCreditsToUser($user->id, $totalCredits, 'SentinelClip');
            }

            return response()->json([
                'success' => false,
                'message' => 'Unable to queue Sentinel-2 clip imagery at this time.',
                'data' => [
                    'current_credits' => $this->creditService->getRemainingCredits($user->id),
                    'required_credits' => $totalCredits,
                ],
            ], 500);
        }
    }

    private function normalizeClipGeometry(array $payload): array
    {
        $type = $payload['type'] ?? null;

        if ($type === 'FeatureCollection') {
            $features = $payload['features'] ?? [];
            if (!is_array($features) || count($features) === 0) {
                return ['type' => 'FeatureCollection', 'features' => []];
            }

            $feature = $features[0];
        } elseif ($type === 'Feature') {
            $feature = $payload;
        } else {
            $feature = [
                'type' => 'Feature',
                'geometry' => $payload,
                'properties' => new \stdClass(),
            ];
        }

        $geometry = $feature['geometry'] ?? null;
        if (!is_array($geometry)) {
            return ['type' => 'FeatureCollection', 'features' => []];
        }

        return [
            'type' => 'FeatureCollection',
            'features' => [
                [
                    'type' => 'Feature',
                    'geometry' => $geometry,
                    'properties' => $feature['properties'] ?? new \stdClass(),
                ],
            ],
        ];
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
