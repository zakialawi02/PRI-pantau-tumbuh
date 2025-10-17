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
use Carbon\Carbon;
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
            'field_name' => ['nullable', 'string', 'max:255'],
            'area_square_meters' => ['required', 'numeric', 'min:1'],
            'start_date' => ['nullable', 'date'],
        ]);

        $geometryInput = $validated['geometry'];
        if (is_string($geometryInput)) {
            $decoded = json_decode($geometryInput, true);
            if (json_last_error() !== JSON_ERROR_NONE || !is_array($decoded)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid geometry payload provided.',
                ], 422);
            }
            $geometryInput = $decoded;
        }

        if (!is_array($geometryInput)) {
            return response()->json([
                'success' => false,
                'message' => 'Geometry payload must be a valid GeoJSON structure.',
            ], 422);
        }

        $geometry = $geometryInput;
        if (isset($geometryInput['type']) && strtolower($geometryInput['type']) === 'feature' && isset($geometryInput['geometry'])) {
            $geometry = $geometryInput['geometry'];
        }

        if (!is_array($geometry) || !isset($geometry['type']) || empty($geometry['coordinates'])) {
            return response()->json([
                'success' => false,
                'message' => 'A valid polygon geometry is required.',
            ], 422);
        }

        $areaSqm = (float) $validated['area_square_meters'];
        if ($areaSqm <= 0) {
            return response()->json([
                'success' => false,
                'message' => 'The provided area must be greater than zero.',
            ], 422);
        }

        $areaHa = $areaSqm / 10000;
        $creditRate = (float) config('app-constants.imagery_credit_cost_per_hectare', 0);
        $estimatedCredits = round(max(0, $areaHa * $creditRate), 2);

        $currentCredits = $this->creditService->getRemainingCredits($user->id);
        if ($estimatedCredits > 0 && $currentCredits < $estimatedCredits) {
            return response()->json([
                'success' => false,
                'message' => 'Insufficient credits to queue Sentinel-2 clipping.',
                'data' => [
                    'current_credits' => $currentCredits,
                    'required_credits' => $estimatedCredits,
                ],
            ], 402);
        }

        try {
            $startDate = !empty($validated['start_date'])
                ? Carbon::parse($validated['start_date'])->startOfDay()
                : now()->subMonth()->startOfDay();
        } catch (Throwable $exception) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid start date provided.',
            ], 422);
        }

        $fieldName = trim($validated['field_name'] ?? '');
        if ($fieldName === '') {
            $fieldName = 'Sentinel Clip ' . now()->format('YmdHis');
        }

        $displayBase = $this->sanitizeDisplayName($fieldName . '_' . now()->format('YmdHis'));
        $outputBase = Str::limit($displayBase . '_clip', 160, '');

        $disk = Storage::disk('public');
        $disk->makeDirectory('imagery');
        $disk->makeDirectory('imagery/clipped');

        $outputFilename = $this->ensureUniqueFilename('imagery', $outputBase, 'tif');
        $storedPath = 'storage/imagery/clipped/' . $outputFilename;

        $feature = [
            'type' => 'Feature',
            'properties' => new \stdClass(),
            'geometry' => $geometry,
        ];

        $fieldArea = null;
        $imagery = null;
        $deductedCredits = false;

        try {
            $fieldArea = FieldArea::create([
                'user_id' => $user->id,
                'name' => $fieldName,
                'area_ha' => round($areaHa, 4),
                'geom' => $feature,
            ]);

            $imagery = ImageryData::create([
                'user_id' => $user->id,
                'source_type' => 'sentinel-2-clip',
                'original_name' => $displayBase . '.tif',
                'stored_name' => $outputFilename,
                'size' => 0,
                'format' => 'tif',
                'path' => $storedPath,
                'upload_status' => 'uploading',
                'processing_status' => 'waiting',
                'uploaded_at' => now(),
            ]);

            if ($estimatedCredits > 0) {
                $deductedCredits = $this->creditService->deductCreditsForProcessing($user->id, $estimatedCredits, 'SentinelClip');

                if (!$deductedCredits) {
                    $currentCredits = $this->creditService->getRemainingCredits($user->id);
                    $imagery->delete();
                    $fieldArea->delete();

                    return response()->json([
                        'success' => false,
                        'message' => 'Insufficient credits to queue Sentinel-2 clipping.',
                        'data' => [
                            'current_credits' => $currentCredits,
                            'required_credits' => $estimatedCredits,
                        ],
                    ], 402);
                }
            }
        } catch (Throwable $exception) {
            Log::error('SentinelProcessingController@processClip failed to initialize clip request.', [
                'error' => $exception->getMessage(),
            ]);

            if ($imagery) {
                $imagery->delete();
            }
            if ($fieldArea) {
                $fieldArea->delete();
            }

            if ($deductedCredits && $estimatedCredits > 0) {
                $this->creditService->addCreditsToUser($user->id, $estimatedCredits, 'SentinelClipInit');
            }

            return response()->json([
                'success' => false,
                'message' => 'Unable to queue Sentinel-2 clip at this time.',
            ], 500);
        }

        ProcessSentinelClipJob::dispatch(
            $imagery->id,
            $fieldArea->id,
            [
                'geometry' => $feature,
                'output_filename' => $outputFilename,
                'start_date' => $startDate->toDateString(),
                'charged_credits' => $estimatedCredits,
                'area_square_meters' => $areaSqm,
                'field_name' => $fieldName,
            ]
        )->onQueue('download');

        $remainingCredits = $this->creditService->getRemainingCredits($user->id);

        Log::info('SentinelProcessingController@processClip queued clipping request.', [
            'imagery_id' => $imagery->id,
            'field_area_id' => $fieldArea->id,
            'user_id' => $user->id,
            'start_date' => $startDate->toDateString(),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Sentinel-2 clip has been queued for processing.',
            'data' => [
                'imagery_id' => $imagery->id,
                'field_area_id' => $fieldArea->id,
                'current_credits' => $remainingCredits,
                'charged_credits' => $estimatedCredits,
                'area_hectares' => round($areaHa, 4),
                'start_date' => $startDate->toDateString(),
            ],
        ], 202);
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
