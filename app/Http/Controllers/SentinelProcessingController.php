<?php

namespace App\Http\Controllers;

use App\Jobs\ProcessSentinelClipJob;
use App\Jobs\ProcessSentinelSceneJob;
use App\Models\ImageryData;
use App\Services\CreditService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use JsonException;
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
            'mode' => ['required', Rule::in(['auto', 'manual'])],
            'geometry' => ['required', 'array'],
            'geometry.type' => ['required', 'string'],
            'geometry.coordinates' => ['required'],
            'area_sq_m' => ['required', 'numeric', 'gt:0'],
            'start_date' => ['nullable', 'date'],
            'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
            'max_cloud' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'product_level' => ['nullable', Rule::in(['S2MSI2A', 'S2MSI1C'])],
            'scene' => ['required_if:mode,manual', 'array'],
            'scene.id' => ['required_if:mode,manual', 'string'],
            'scene.datetime' => ['required_if:mode,manual', 'string'],
        ]);

        $geometry = $validated['geometry'];
        $areaSquareMeters = (float) $validated['area_sq_m'];
        $areaHectares = $areaSquareMeters / 10000;
        $creditRate = config('app-constants.imagery_credit_cost_per_hectare', 0.0);
        $creditCost = round($areaHectares * $creditRate, 2);

        if ($creditCost <= 0) {
            return response()->json([
                'success' => false,
                'message' => 'Unable to determine credit cost for the selected area.',
            ], 422);
        }

        $startDate = $validated['start_date']
            ? Carbon::parse($validated['start_date'])->startOfDay()
            : now()->copy()->subMonths(2)->startOfDay();

        $endDate = $validated['end_date']
            ? Carbon::parse($validated['end_date'])->endOfDay()
            : now()->copy()->endOfDay();

        if ($startDate->greaterThan($endDate)) {
            [$startDate, $endDate] = [$endDate->copy()->startOfDay(), $startDate->copy()->endOfDay()];
        }

        $remainingCredits = $this->creditService->getRemainingCredits($user->id);
        if ($remainingCredits < $creditCost) {
            return response()->json([
                'success' => false,
                'message' => 'Insufficient credit points to process the requested clip.',
                'current_credits' => $remainingCredits,
                'required_credits' => $creditCost,
            ], 422);
        }

        $deducted = $this->creditService->deductCreditsForProcessing($user->id, $creditCost, 'Clip');

        if (!$deducted) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to deduct credit points for the clip processing request.',
                'current_credits' => $remainingCredits,
            ], 422);
        }

        $displayName = $this->sanitizeDisplayName('Sentinel2_Clip_' . now()->format('YmdHis'));
        $outputFilename = $this->ensureUniqueFilename('imagery/clip', $displayName, 'tif');
        $publicPath = 'storage/imagery/clip/' . $outputFilename;

        try {
            $geometryJson = json_encode($geometry, JSON_THROW_ON_ERROR);

            $imagery = ImageryData::create([
                'user_id' => $user->id,
                'source_type' => 'sentinel-2-clip',
                'original_name' => $displayName . '.tif',
                'stored_name' => $outputFilename,
                'size' => 0,
                'format' => 'tif',
                'path' => $publicPath,
                'upload_status' => 'uploading',
                'processing_status' => 'waiting',
                'uploaded_at' => now(),
            ]);

            $options = [
                'geometry' => $geometryJson,
                'date_from' => $startDate->toIso8601String(),
                'date_to' => $endDate->toIso8601String(),
                'max_cloud' => $validated['max_cloud'] ?? null,
                'product_level' => $validated['product_level'] ?? 'S2MSI2A',
                'limit' => 60,
                'resolution' => 10,
                'nodata' => 0,
            ];

            if ($validated['mode'] === 'manual' && !empty($validated['scene'])) {
                $options['scene_id'] = $validated['scene']['id'] ?? null;
                $options['scene_datetime'] = $validated['scene']['datetime'] ?? null;
            }

            ProcessSentinelClipJob::dispatch($imagery->id, $options)->onQueue('download');

            return response()->json([
                'success' => true,
                'message' => 'Sentinel clip request queued for processing.',
                'data' => [
                    'imagery_id' => $imagery->id,
                    'credit_cost' => $creditCost,
                    'current_credits' => $this->creditService->getRemainingCredits($user->id),
                ],
            ], 202);
        } catch (JsonException $exception) {
            Log::warning('SentinelProcessingController@processClip invalid geometry payload.', [
                'error' => $exception->getMessage(),
            ]);

            if (isset($imagery)) {
                $imagery->update([
                    'upload_status' => 'failed',
                    'processing_status' => 'error',
                ]);
            }

            $this->creditService->addCreditsToUser($user->id, $creditCost, 'Clip');

            return response()->json([
                'success' => false,
                'message' => 'Unable to encode the provided geometry for processing.',
                'current_credits' => $this->creditService->getRemainingCredits($user->id),
            ], 422);
        } catch (Throwable $exception) {
            Log::error('SentinelProcessingController@processClip failed.', [
                'error' => $exception->getMessage(),
            ]);

            if (isset($imagery)) {
                $imagery->update([
                    'upload_status' => 'failed',
                    'processing_status' => 'error',
                ]);
            }

            $this->creditService->addCreditsToUser($user->id, $creditCost, 'Clip');

            return response()->json([
                'success' => false,
                'message' => 'Unable to queue Sentinel clip processing at this time.',
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
