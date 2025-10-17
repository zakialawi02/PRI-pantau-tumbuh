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
    public function __construct(protected CreditService $creditService) {}

    public function processClip(Request $request)
    {
        $user = $request->user();

        $validated = $request->validate([
            'field_name' => ['nullable', 'string', 'max:255'],
            'geometry' => ['required'],
            'area_hectares' => ['required', 'numeric', 'gt:0'],
            'scene' => ['required', 'array'],
            'scene.product_id' => ['nullable', 'string', 'max:255'],
            'scene.id' => ['nullable', 'string', 'max:255'],
            'scene.title' => ['nullable', 'string', 'max:255'],
            'scene.collection' => ['nullable', 'string', 'max:255'],
            'scene.acquired_at' => ['required', 'date'],
            'scene.cloud_cover' => ['nullable', 'numeric'],
            'scene.tile' => ['nullable', 'string', 'max:50'],
        ]);

        $geometry = $this->normalizeGeometry($validated['geometry']);
        $areaHectares = (float) $validated['area_hectares'];

        if ($areaHectares <= 0) {
            return response()->json([
                'success' => false,
                'message' => 'Area must be greater than zero.',
            ], 422);
        }

        $creditRate = (float) config('app-constants.imagery_credit_cost_per_hectare', 0.0);
        $requiredCredits = round($areaHectares * max($creditRate, 0), 2);

        if ($requiredCredits <= 0) {
            return response()->json([
                'success' => false,
                'message' => 'Unable to determine the required credit cost for this request.',
            ], 422);
        }

        if (!$this->creditService->deductCreditsForProcessing($user->id, $requiredCredits, 'SentinelClipController')) {
            return response()->json([
                'success' => false,
                'message' => 'Insufficient credit points to process this imagery.',
                'current_credits' => $this->creditService->getRemainingCredits($user->id),
            ], 422);
        }

        DB::beginTransaction();

        try {
            $fieldArea = FieldArea::create([
                'user_id' => $user->id,
                'name' => $validated['field_name'] ?? $validated['scene']['title'] ?? null,
                'area_ha' => $areaHectares,
                'geom' => json_encode($geometry, JSON_UNESCAPED_UNICODE),
            ]);

            $storedName = $this->generateStoredName($validated['field_name'] ?? $validated['scene']['title'] ?? null);
            $originalName = trim(($validated['scene']['title'] ?? $storedName), '_') . '.tif';

            $imagery = ImageryData::create([
                'user_id' => $user->id,
                'source_type' => 'sentinel-2-clip',
                'original_name' => $originalName,
                'stored_name' => $storedName,
                'size' => 0,
                'format' => 'tif',
                'path' => 'storage/imagery/' . $storedName,
                'upload_status' => 'uploading',
                'processing_status' => 'waiting',
                'uploaded_at' => now(),
            ]);

            DB::commit();
        } catch (Throwable $exception) {
            DB::rollBack();

            Log::error('SentinelClipController@processClip failed to persist records.', [
                'message' => $exception->getMessage(),
            ]);

            $this->creditService->addCreditsToUser($user->id, $requiredCredits, 'SentinelClipController');

            return response()->json([
                'success' => false,
                'message' => 'Unable to queue Sentinel clip processing at this time.',
            ], 500);
        }

        try {
            $payload = [
                'geometry' => $geometry,
                'scene' => $validated['scene'],
                'area_hectares' => $areaHectares,
                'field_name' => $validated['field_name'] ?? null,
                'credit_cost' => $requiredCredits,
            ];

            ProcessSentinelClipJob::dispatch(
                $imagery->id,
                $fieldArea->id,
                $payload
            )->onQueue('download')->afterCommit();

            return response()->json([
                'success' => true,
                'message' => 'Sentinel clip imagery has been queued for processing.',
                'data' => [
                    'imagery_id' => $imagery->id,
                    'field_area_id' => $fieldArea->id,
                    'credit_cost' => $requiredCredits,
                    'current_credits' => $this->creditService->getRemainingCredits($user->id),
                ],
            ], 202);
        } catch (Throwable $exception) {
            Log::error('SentinelClipController@processClip failed to dispatch job.', [
                'message' => $exception->getMessage(),
            ]);

            $this->creditService->addCreditsToUser($user->id, $requiredCredits, 'SentinelClipController');

            $imagery->update([
                'upload_status' => 'failed',
                'processing_status' => 'error',
            ]);

            $imagery->delete();
            $fieldArea->delete();

            return response()->json([
                'success' => false,
                'message' => 'Unable to queue Sentinel clip processing at this time.',
            ], 500);
        }
    }

    private function normalizeGeometry($geometry): array
    {
        if (is_array($geometry)) {
            return $geometry;
        }

        if (is_string($geometry)) {
            $decoded = json_decode($geometry, true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                return $decoded;
            }
        }

        throw new \InvalidArgumentException('Invalid GeoJSON geometry payload.');
    }

    private function generateStoredName(?string $preferred): string
    {
        $base = Str::slug($preferred ?? 'sentinel-clip');
        $base = $base !== '' ? $base : 'sentinel-clip';
        $timestamp = now()->format('YmdHis');

        return Str::limit($base, 100, '') . '-' . $timestamp . '.tif';
    }
}
