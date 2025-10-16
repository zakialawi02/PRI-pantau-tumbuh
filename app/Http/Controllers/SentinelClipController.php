<?php

namespace App\Http\Controllers;

use App\Jobs\ProcessSentinelClipJob;
use App\Models\FieldArea;
use App\Models\ImageryData;
use App\Services\CreditService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

class SentinelClipController extends Controller
{
    public function __construct(protected CreditService $creditService)
    {
    }

    public function processClip(Request $request)
    {
        $user = $request->user();

        $validated = $request->validate([
            'field_name' => ['required', 'string', 'max:255'],
            'geometry' => ['required', 'array'],
            'area_hectares' => ['required', 'numeric', 'min:0.01'],
            'mode' => ['required', 'in:auto,manual'],
            'date_from' => ['required', 'date'],
            'date_to' => ['required', 'date', 'after_or_equal:date_from'],
            'max_cloud' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:200'],
            'resolution' => ['nullable', 'integer', 'in:10,20,60'],
            'nodata' => ['nullable', 'numeric'],
            'selected_scene' => ['nullable', 'array'],
        ]);

        if ($validated['mode'] === 'manual' && empty($validated['selected_scene'])) {
            return response()->json([
                'success' => false,
                'message' => 'Please choose a Sentinel-2 scene before processing.',
            ], 422);
        }

        $creditRate = config('app-constants.imagery_credit_cost_per_hectare', 0.8);
        $creditCost = round($validated['area_hectares'] * $creditRate, 2);

        if ($creditCost <= 0) {
            return response()->json([
                'success' => false,
                'message' => 'Unable to process an area smaller than the minimum requirement.',
            ], 422);
        }

        try {
            $result = DB::transaction(function () use ($user, $validated, $creditCost) {
                if (!$this->creditService->deductCreditsForProcessing($user->id, $creditCost, 'SentinelClip')) {
                    return null;
                }

                $fieldArea = FieldArea::create([
                    'user_id' => $user->id,
                    'name' => $validated['field_name'],
                    'geom' => $validated['geometry'],
                    'area_ha' => $validated['area_hectares'],
                ]);

                $displayBase = Str::slug($validated['field_name']) ?: 'sentinel-clip';
                $timestamp = now()->format('YmdHis');
                $outputFilename = sprintf('%s-%s.tif', $displayBase, $timestamp);

                $imagery = ImageryData::create([
                    'user_id' => $user->id,
                    'source_type' => 'sentinel-2-clip',
                    'original_name' => $outputFilename,
                    'stored_name' => $outputFilename,
                    'size' => 0,
                    'format' => 'tif',
                    'path' => 'storage/imagery/clipped/' . $outputFilename,
                    'upload_status' => 'uploading',
                    'processing_status' => 'waiting',
                    'uploaded_at' => now(),
                ]);

                $payload = [
                    'geometry' => $validated['geometry'],
                    'date_from' => $validated['date_from'],
                    'date_to' => $validated['date_to'],
                    'max_cloud' => $validated['max_cloud'] ?? 60,
                    'limit' => $validated['limit'] ?? 50,
                    'resolution' => $validated['resolution'] ?? 10,
                    'nodata' => $validated['nodata'] ?? 0,
                    'mode' => $validated['mode'],
                    'scene' => $validated['selected_scene'] ?? [],
                    'output_filename' => $outputFilename,
                ];

                ProcessSentinelClipJob::dispatch($imagery->id, $fieldArea->id, $payload)->onQueue('download');

                return [$fieldArea, $imagery];
            });

            if ($result === null) {
                return response()->json([
                    'success' => false,
                    'message' => 'Insufficient credit points for this request.',
                ], 422);
            }

            [$fieldArea, $imagery] = $result;

            return response()->json([
                'success' => true,
                'message' => 'Clipped Sentinel-2 imagery queued for processing.',
                'data' => [
                    'field_area_id' => $fieldArea->id,
                    'imagery_id' => $imagery->id,
                    'credit_cost' => $creditCost,
                    'current_credits' => $this->creditService->getRemainingCredits($user->id),
                ],
            ], 202);
        } catch (Throwable $exception) {
            Log::error('SentinelClipController@processClip failed.', [
                'error' => $exception->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Unable to queue Sentinel-2 clipping request at this time.',
            ], 500);
        }
    }
}
