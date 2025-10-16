<?php

namespace App\Http\Controllers;

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
            'download_url' => ['required_without:clip_mode', 'nullable', 'url'],
            'product_id' => ['nullable', 'string', 'max:255'],
            'collection' => ['nullable', 'string', 'max:255'],
            'acquisition_date' => ['nullable', 'string', 'max:255'],
            'download_filename' => ['nullable', 'string', 'max:255'],
            'clip_mode' => ['sometimes', 'boolean'],
            'clip_geometry' => ['required_if:clip_mode,true'],
            'clip_date_from' => ['required_if:clip_mode,true', 'date'],
            'clip_date_to' => ['required_if:clip_mode,true', 'date', 'after_or_equal:clip_date_from'],
            'clip_max_cloud' => ['nullable', 'numeric', 'between:0,100'],
            'clip_limit' => ['nullable', 'integer', 'min:1', 'max:100'],
            'clip_resolution' => ['nullable', 'integer', 'min:10', 'max:60'],
            'clip_nodata' => ['nullable', 'numeric'],
            'clip_product_level' => ['nullable', 'in:S2MSI2A,S2MSI1C'],
            'clip_scene_id' => ['nullable', 'string', 'max:255'],
            'clip_selection_mode' => ['nullable', 'in:auto,manual'],
            'clip_selected_product_id' => ['nullable', 'string', 'max:255'],
            'clip_selected_acquisition' => ['nullable', 'string', 'max:255'],
            'field_name' => ['nullable', 'string', 'max:255'],
            'area_hectares' => ['required_if:clip_mode,true', 'numeric', 'min:0'],
            'clip_total_credits' => ['nullable', 'numeric', 'min:0'],
        ]);

        $clipMode = filter_var($validated['clip_mode'] ?? false, FILTER_VALIDATE_BOOLEAN);

        $geometryPayload = null;
        $areaHa = null;
        $creditCharge = null;
        $defaultCharge = config('app-constants.imagery_processing_cost', 10);
        $fieldArea = null;

        if ($clipMode) {
            $geometryPayload = $validated['clip_geometry'];
            if (is_string($geometryPayload)) {
                $decoded = json_decode($geometryPayload, true);
                if (json_last_error() !== JSON_ERROR_NONE || empty($decoded)) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Invalid clip geometry supplied.',
                    ], 422);
                }
                $geometryPayload = $decoded;
            }

            if (!is_array($geometryPayload)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Clip geometry must be a valid GeoJSON object.',
                ], 422);
            }

            $areaHa = (float) $validated['area_hectares'];
            $creditPerHa = config('app-constants.imagery_credit_cost_per_hectare', 0);
            $creditCharge = round($areaHa * $creditPerHa, 2);
            if ($creditCharge < 0) {
                $creditCharge = 0.0;
            }
        }

        if (!$this->creditService->deductCreditsForProcessing($user->id, $clipMode ? $creditCharge : null)) {
            return response()->json([
                'success' => false,
                'message' => 'Insufficient credit points to start processing.',
                'current_credits' => $this->creditService->getRemainingCredits($user->id),
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

        try {
            if ($clipMode) {
                $fieldArea = FieldArea::create([
                    'user_id' => $user->id,
                    'name' => $validated['field_name'] ?: ('Clip Area ' . now()->format('YmdHis')),
                    'area_ha' => $areaHa,
                    'geom' => $geometryPayload,
                ]);
            }

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
                $clipMode ? null : ($validated['download_url'] ?? null),
                $sentinelDirectory,
                $zipFilename,
                $outputFilename,
                $finalDisplayName,
                [
                    'product_id' => $validated['product_id'] ?? null,
                    'collection' => $validated['collection'] ?? null,
                    'acquisition_date' => $validated['acquisition_date'] ?? null,
                    'download_filename' => $validated['download_filename'] ?? null,
                    'clip_mode' => $clipMode,
                    'clip_geometry' => $geometryPayload,
                    'clip_date_from' => $validated['clip_date_from'] ?? null,
                    'clip_date_to' => $validated['clip_date_to'] ?? null,
                    'clip_max_cloud' => $validated['clip_max_cloud'] ?? null,
                    'clip_limit' => $validated['clip_limit'] ?? null,
                    'clip_resolution' => $validated['clip_resolution'] ?? null,
                    'clip_nodata' => $validated['clip_nodata'] ?? null,
                    'clip_product_level' => $validated['clip_product_level'] ?? null,
                    'clip_scene_id' => $validated['clip_scene_id'] ?? null,
                    'clip_selection_mode' => $validated['clip_selection_mode'] ?? null,
                    'clip_selected_product_id' => $validated['clip_selected_product_id'] ?? null,
                    'clip_selected_acquisition' => $validated['clip_selected_acquisition'] ?? null,
                    'clip_field_area_id' => $fieldArea?->id,
                    'clip_area_hectares' => $areaHa,
                    'credit_charge' => $clipMode ? $creditCharge : $defaultCharge,
                ]
            )->onQueue('download');

            return response()->json([
                'success' => true,
                'message' => 'Sentinel scene queued for processing.',
                'data' => [
                    'id' => $imagery->id,
                    'upload_status' => $imagery->upload_status,
                    'processing_status' => $imagery->processing_status,
                    'current_credits' => $this->creditService->getRemainingCredits($user->id),
                    'field_area_id' => $fieldArea?->id,
                    'credit_charge' => $clipMode ? $creditCharge : $defaultCharge,
                ],
            ], 202);
        } catch (Throwable $exception) {
            Log::error('SentinelProcessingController@store failed to queue processing.', [
                'error' => $exception->getMessage(),
                'trace' => $exception->getTraceAsString(),
            ]);

            if (isset($fieldArea)) {
                $fieldArea->delete();
            }

            if (isset($imagery)) {
                $imagery->update([
                    'upload_status' => 'failed',
                    'processing_status' => 'error',
                ]);
            }

            if ($clipMode) {
                $this->creditService->addCreditsToUser($user->id, $creditCharge, 'Controller');
            } else {
                $this->creditService->addCreditsToUser($user->id, $defaultCharge, 'Controller');
            }

            return response()->json([
                'success' => false,
                'message' => 'Unable to queue Sentinel imagery processing at this time.',
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
