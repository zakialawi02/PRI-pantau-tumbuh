<?php

namespace App\Http\Controllers;

use App\Jobs\ProcessSentinelClipJob;
use App\Jobs\ProcessSentinelSceneJob;
use App\Models\FieldArea;
use App\Models\ImageryData;
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

        $geometryInput = $request->input('geometry');
        if (is_string($geometryInput)) {
            try {
                $decodedGeometry = json_decode($geometryInput, true, 512, JSON_THROW_ON_ERROR);
                $request->merge(['geometry' => $decodedGeometry]);
            } catch (Throwable $exception) {
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid geometry provided. Please redraw your polygon.',
                ], 422);
            }
        }

        $validated = $request->validate([
            'field_name' => ['required', 'string', 'max:120'],
            'geometry' => ['required', 'array'],
            'geometry.type' => ['required', 'string'],
            'geometry.features' => ['sometimes', 'array'],
            'geometry.geometry' => ['sometimes', 'array'],
            'area_hectares' => ['required', 'numeric', 'gt:0'],
            'area_square_meters' => ['nullable', 'numeric', 'gt:0'],
            'credit_cost' => ['nullable', 'numeric', 'gte:0'],
        ]);

        $geometry = $validated['geometry'] ?? null;
        if (!is_array($geometry)) {
            return response()->json([
                'success' => false,
                'message' => 'Unable to parse the submitted geometry.',
            ], 422);
        }

        $featureCollection = null;
        if (($geometry['type'] ?? null) === 'FeatureCollection') {
            $featureCollection = $geometry;
        } elseif (($geometry['type'] ?? null) === 'Feature') {
            $featureCollection = [
                'type' => 'FeatureCollection',
                'features' => [$geometry],
            ];
        }

        if (!$featureCollection || empty($featureCollection['features'][0]['geometry'])) {
            return response()->json([
                'success' => false,
                'message' => 'Draw a valid polygon before processing Sentinel-2 clips.',
            ], 422);
        }

        $fieldName = trim($validated['field_name']);
        $areaHa = (float) $validated['area_hectares'];
        $creditRate = (float) config('app-constants.imagery_credit_cost_per_hectare', 0);
        $creditCost = array_key_exists('credit_cost', $validated)
            ? (float) $validated['credit_cost']
            : $areaHa * $creditRate;
        $creditCost = round(max($creditCost, 0), 2);

        $currentCredits = $this->creditService->getRemainingCredits($user->id);
        if ($creditCost > 0 && $currentCredits < $creditCost) {
            return response()->json([
                'success' => false,
                'message' => 'Insufficient credits to clip Sentinel imagery.',
                'data' => [
                    'current_credits' => $currentCredits,
                    'required_credits' => $creditCost,
                ],
            ], 402);
        }

        $deducted = false;
        $fieldArea = null;
        $imagery = null;

        try {
            if ($creditCost > 0) {
                $deducted = $this->creditService->deductCreditsForProcessing($user->id, $creditCost, 'SentinelClip');
                if (!$deducted) {
                    $currentCredits = $this->creditService->getRemainingCredits($user->id);
                    return response()->json([
                        'success' => false,
                        'message' => 'Insufficient credits to clip Sentinel imagery.',
                        'data' => [
                            'current_credits' => $currentCredits,
                            'required_credits' => $creditCost,
                        ],
                    ], 402);
                }
            }

            DB::beginTransaction();

            $fieldArea = FieldArea::create([
                'user_id' => $user->id,
                'name' => $fieldName,
                'area_ha' => $areaHa,
                'geom' => $featureCollection,
            ]);

            $baseName = $this->sanitizeDisplayName($fieldName);
            $timestampedBase = Str::limit($baseName . '_' . now()->format('YmdHis'), 120, '');
            $clipFilename = $this->ensureUniqueFilename('imagery/clipped', $timestampedBase, 'tif');
            $storedName = 'clipped/' . $clipFilename;
            $publicPath = 'storage/imagery/' . $storedName;

            $imagery = ImageryData::create([
                'user_id' => $user->id,
                'source_type' => 'sentinel-2-clip',
                'original_name' => $clipFilename,
                'stored_name' => $storedName,
                'size' => 0,
                'format' => 'tif',
                'path' => $publicPath,
                'upload_status' => 'processing',
                'processing_status' => 'waiting',
                'uploaded_at' => now(),
            ]);

            DB::commit();
        } catch (Throwable $exception) {
            DB::rollBack();

            if ($deducted && $creditCost > 0) {
                $this->creditService->addCreditsToUser($user->id, $creditCost, 'SentinelClipRollback');
            }

            Log::error('SentinelProcessingController@processClip failed to initialise clip workflow.', [
                'error' => $exception->getMessage(),
                'trace' => $exception->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Unable to queue Sentinel clip at this time.',
                'data' => [
                    'current_credits' => $this->creditService->getRemainingCredits($user->id),
                    'required_credits' => $creditCost,
                ],
            ], 500);
        }

        try {
            ProcessSentinelClipJob::dispatch($imagery->id, $fieldArea->id, [
                'geometry' => $featureCollection,
                'area_hectares' => $areaHa,
                'credit_cost' => $creditCost,
                'field_name' => $fieldName,
                'output_filename' => $storedName,
            ])->onQueue('processing');
        } catch (Throwable $exception) {
            Log::error('SentinelProcessingController@processClip failed to dispatch job.', [
                'error' => $exception->getMessage(),
                'trace' => $exception->getTraceAsString(),
            ]);

            if ($imagery) {
                $imagery->delete();
            }

            if ($fieldArea) {
                $fieldArea->delete();
            }

            if ($deducted && $creditCost > 0) {
                $this->creditService->addCreditsToUser($user->id, $creditCost, 'SentinelClipDispatch');
            }

            return response()->json([
                'success' => false,
                'message' => 'Unable to queue Sentinel clip at this time.',
                'data' => [
                    'current_credits' => $this->creditService->getRemainingCredits($user->id),
                    'required_credits' => $creditCost,
                ],
            ], 500);
        }

        $remainingCredits = $this->creditService->getRemainingCredits($user->id);

        return response()->json([
            'success' => true,
            'message' => 'Sentinel clip queued for processing.',
            'data' => [
                'imagery_id' => $imagery->id,
                'field_area_id' => $fieldArea->id,
                'current_credits' => $remainingCredits,
                'required_credits' => $creditCost,
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
