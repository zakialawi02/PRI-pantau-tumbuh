<?php

namespace App\Http\Controllers;

use App\Jobs\ProcessSentinelClipJob;
use App\Models\FieldArea;
use App\Models\ImageryData;
use App\Services\CopernicusTokenService;
use App\Services\CreditService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Throwable;

class SentinelClipController extends Controller
{
    public function __construct(protected CreditService $creditService) {}

    public function searchScenes(Request $request): JsonResponse
    {
        $user = $request->user();

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Authentication is required to search Sentinel scenes.',
            ], 401);
        }

        $validated = $request->validate([
            'geometry' => ['required'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:10'],
            'max_cloud_cover' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'collection' => ['nullable', 'string'],
            'start_date' => ['nullable', 'date'],
            'end_date' => ['nullable', 'date'],
        ]);

        $geometry = $this->normalizeGeometry($validated['geometry'] ?? null);

        if (!$geometry) {
            throw ValidationException::withMessages([
                'geometry' => 'A valid polygon GeoJSON is required.',
            ]);
        }

        $limit = (int) ($validated['limit'] ?? 5);
        $limit = $limit > 0 ? min($limit, 10) : 5;

        $maxCloud = isset($validated['max_cloud_cover']) ? (float) $validated['max_cloud_cover'] : null;

        $endDate = $validated['end_date'] ?? null;
        $startDate = $validated['start_date'] ?? null;

        $end = $endDate ? Carbon::parse($endDate)->endOfDay() : Carbon::now();
        $start = $startDate ? Carbon::parse($startDate)->startOfDay() : $end->copy()->subDays(30);

        $collection = strtolower($validated['collection'] ?? 'sentinel-2-l2a');

        $token = CopernicusTokenService::getAccessToken();
        if (!$token) {
            return response()->json([
                'success' => false,
                'message' => 'Copernicus credentials are not configured.',
            ], 503);
        }

        $endpoint = (string) config('services.copernicus.catalog_endpoint', 'https://sh.dataspace.copernicus.eu/api/v1/catalog/1.0.0/search');

        $body = [
            'collections' => [$collection],
            'limit' => $limit,
            'intersects' => $geometry,
            'datetime' => sprintf('%s/%s', $start->toIso8601String(), $end->toIso8601String()),
            'sort' => [
                [
                    'field' => 'properties.datetime',
                    'direction' => 'desc',
                ],
            ],
            'fields' => [
                'include' => [
                    'id',
                    'bbox',
                    'collection',
                    'geometry',
                    'properties.datetime',
                    'properties.eo:cloud_cover',
                    'properties.cloudCover',
                    'properties.title',
                    'properties.productIdentifier',
                    'properties.constellation',
                    'properties.platform',
                    'properties.orbitNumber',
                    'properties.processingBaseline',
                    'assets.thumbnail',
                    'assets.preview',
                    'assets.quicklook',
                ],
            ],
        ];

        if ($maxCloud !== null) {
            $body['query'] = [
                'eo:cloud_cover' => [
                    'lt' => $maxCloud,
                ],
            ];
        }

        try {
            $response = Http::withToken($token)
                ->acceptJson()
                ->post($endpoint, $body);
        } catch (Throwable $exception) {
            Log::error('SentinelClipController@searchScenes request failed.', [
                'message' => $exception->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Unable to query Copernicus catalogue.',
            ], 502);
        }

        if (!$response->successful()) {
            Log::warning('SentinelClipController@searchScenes catalogue error.', [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Copernicus catalogue responded with an error.',
            ], $response->status());
        }

        $payload = $response->json();
        $features = is_array($payload['features'] ?? null) ? $payload['features'] : [];

        $scenes = [];
        foreach ($features as $feature) {
            $scene = $this->transformSceneFeature($feature);
            if ($scene !== null) {
                $scenes[] = $scene;
            }
        }

        return response()->json([
            'success' => true,
            'data' => [
                'scenes' => $scenes,
            ],
        ]);
    }

    public function processClip(Request $request): JsonResponse
    {
        $user = $request->user();

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Authentication is required to process Sentinel clips.',
            ], 401);
        }

        $validated = $request->validate([
            'field_name' => ['nullable', 'string', 'max:255'],
            'geometry' => ['required'],
            'area_hectares' => ['required', 'numeric', 'gt:0'],
            'area_square_meters' => ['nullable', 'numeric', 'gt:0'],
            'scene' => ['required', 'array'],
            'scene.id' => ['required', 'string'],
            'scene.datetime' => ['nullable', 'string'],
            'scene.collection' => ['nullable', 'string'],
            'scene.title' => ['nullable', 'string'],
            'scene.cloud_cover' => ['nullable'],
            'scene.platform' => ['nullable', 'string'],
            'scene.orbit' => ['nullable'],
            'credit_rate' => ['nullable', 'numeric', 'min:0'],
            'credit_cost' => ['nullable', 'numeric', 'min:0'],
        ]);

        $geometry = $this->normalizeGeometry($validated['geometry'] ?? null);
        if (!$geometry) {
            throw ValidationException::withMessages([
                'geometry' => 'A valid polygon GeoJSON is required.',
            ]);
        }

        $areaHa = (float) $validated['area_hectares'];
        $areaSqm = isset($validated['area_square_meters']) ? (float) $validated['area_square_meters'] : $areaHa * 10000;

        $creditRate = $validated['credit_rate'] ?? config('app-constants.imagery_credit_cost_per_hectare', 0.8);
        $calculatedCost = round($areaHa * $creditRate, 2);
        $creditCost = round(max($calculatedCost, (float) ($validated['credit_cost'] ?? 0)), 2);

        $scene = $validated['scene'];
        $sceneId = (string) $scene['id'];
        $sceneDatetime = $scene['datetime'] ?? null;

        if ($sceneDatetime) {
            try {
                $sceneDatetime = Carbon::parse($sceneDatetime)->toIso8601String();
            } catch (Throwable $exception) {
                $sceneDatetime = null;
            }
        }

        $collection = strtolower($scene['collection'] ?? 'sentinel-2-l2a');

        if (!$this->creditService->deductCreditsForProcessing($user->id, $creditCost, 'SentinelClip')) {
            throw ValidationException::withMessages([
                'credits' => 'Insufficient credit points to process this Sentinel clip.',
            ]);
        }

        DB::beginTransaction();

        try {
            $fieldName = trim((string) ($validated['field_name'] ?? ''));
            $fieldArea = FieldArea::create([
                'user_id' => $user->id,
                'name' => $fieldName !== '' ? $fieldName : ('Sentinel Clip ' . now()->format('Y-m-d H:i')),
                'area_ha' => $areaHa,
                'geom' => $geometry,
            ]);

            $storedName = (string) Str::uuid() . '.tif';
            $title = $scene['title'] ?? 'Sentinel Clip';

            $imagery = ImageryData::create([
                'user_id' => $user->id,
                'source_type' => $collection ?: 'sentinel-2',
                'original_name' => $title,
                'stored_name' => $storedName,
                'size' => 0,
                'format' => 'tif',
                'path' => 'storage/imagery/' . $storedName,
                'upload_status' => 'uploading',
                'processing_status' => 'waiting',
                'uploaded_at' => now(),
            ]);

            DB::commit();

            $payload = [
                'geometry' => $geometry,
                'area_hectares' => $areaHa,
                'area_square_meters' => $areaSqm,
                'field_area_id' => $fieldArea->id,
                'credit_cost' => $creditCost,
                'scene' => [
                    'id' => $sceneId,
                    'datetime' => $sceneDatetime,
                    'collection' => $collection,
                    'title' => $title,
                    'cloud_cover' => $scene['cloud_cover'] ?? null,
                    'platform' => $scene['platform'] ?? null,
                    'orbit' => $scene['orbit'] ?? null,
                ],
            ];

            ProcessSentinelClipJob::dispatch($imagery->id, $fieldArea->id, $payload)->onQueue('processing');

            $currentCredits = $this->creditService->getRemainingCredits($user->id);

            return response()->json([
                'success' => true,
                'message' => 'Sentinel clip queued for processing.',
                'data' => [
                    'imagery_id' => $imagery->id,
                    'field_area_id' => $fieldArea->id,
                    'current_credits' => $currentCredits,
                ],
            ]);
        } catch (Throwable $exception) {
            DB::rollBack();

            Log::error('SentinelClipController@processClip failed.', [
                'message' => $exception->getMessage(),
            ]);

            $this->creditService->addCreditsToUser($user->id, $creditCost, 'SentinelClip rollback');

            return response()->json([
                'success' => false,
                'message' => 'Unable to queue Sentinel clip processing at this time.',
            ], 500);
        }
    }

    protected function normalizeGeometry(mixed $geometry): ?array
    {
        if (is_string($geometry)) {
            $decoded = json_decode($geometry, true);
            $geometry = is_array($decoded) ? $decoded : null;
        }

        if (!is_array($geometry)) {
            return null;
        }

        if (($geometry['type'] ?? '') === 'Feature' && isset($geometry['geometry'])) {
            $geometry = $geometry['geometry'];
        }

        $type = strtoupper((string) ($geometry['type'] ?? ''));
        if (!in_array($type, ['POLYGON', 'MULTIPOLYGON'], true)) {
            return null;
        }

        return $geometry;
    }

    protected function transformSceneFeature(array $feature): ?array
    {
        $id = (string) ($feature['id'] ?? '');
        if ($id === '') {
            return null;
        }

        $properties = $feature['properties'] ?? [];
        $datetime = $properties['datetime'] ?? ($properties['startDate'] ?? null);

        $cloud = $properties['eo:cloud_cover'] ?? ($properties['cloudCover'] ?? null);
        if ($cloud !== null) {
            $cloud = (float) $cloud;
        }

        return [
            'id' => $id,
            'title' => $properties['title'] ?? $properties['productIdentifier'] ?? $id,
            'collection' => $feature['collection'] ?? ($properties['collection'] ?? 'sentinel-2-l2a'),
            'datetime' => $datetime,
            'cloud_cover' => $cloud,
            'platform' => $properties['platform'] ?? null,
            'constellation' => $properties['constellation'] ?? null,
            'orbit' => $properties['orbitNumber'] ?? null,
            'processing_baseline' => $properties['processingBaseline'] ?? null,
            'preview' => Arr::get($feature, 'assets.preview.href') ?? Arr::get($feature, 'assets.thumbnail.href') ?? Arr::get($feature, 'assets.quicklook.href'),
            'thumbnail' => Arr::get($feature, 'assets.thumbnail.href') ?? Arr::get($feature, 'assets.quicklook.href'),
            'bbox' => $feature['bbox'] ?? null,
        ];
    }
}
