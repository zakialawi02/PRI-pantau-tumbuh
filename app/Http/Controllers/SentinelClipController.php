<?php

namespace App\Http\Controllers;

use App\Jobs\ProcessSentinelSceneJob;
use App\Models\FieldArea;
use App\Models\ImageryData;
use App\Services\CopernicusTokenService;
use App\Services\CreditService;
use App\Support\FilenameHelper;
use App\Support\GeometryHelper;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;
use Throwable;

class SentinelClipController extends Controller
{
    public function __construct(protected CreditService $creditService)
    {
    }

    public function search(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'geometry' => ['required'],
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after_or_equal:start_date'],
            'max_cloud' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:50'],
            'product_type' => ['nullable', 'string', 'max:50'],
        ]);

        try {
            $geometry = GeometryHelper::normalizeGeometry($validated['geometry']);
        } catch (InvalidArgumentException $exception) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid geometry supplied.',
                'error' => $exception->getMessage(),
            ], 422);
        }

        $start = Carbon::parse($validated['start_date'])->startOfDay()->utc();
        $end = Carbon::parse($validated['end_date'])->endOfDay()->utc();
        $limit = $validated['limit'] ?? 20;
        $maxCloud = $validated['max_cloud'];
        $productType = $validated['product_type'] ?? 'S2MSI2A';

        try {
            $scenes = $this->performSearch($geometry, $start, $end, $limit, $maxCloud, $productType);
        } catch (Throwable $exception) {
            Log::error('SentinelClipController@search failed to query catalogue.', [
                'error' => $exception->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Unable to contact the Sentinel-2 catalogue at this time.',
            ], 502);
        }

        $areaSqm = GeometryHelper::calculateAreaSquareMeters($geometry);
        $areaHa = $areaSqm / 10000;
        $clipCostPerHa = config('app-constants.imagery_credit_cost_per_hectare', 1);
        $clipCost = round($areaHa * $clipCostPerHa, 2);
        $processingCost = config('app-constants.imagery_processing_cost', 10);

        return response()->json([
            'success' => true,
            'data' => [
                'scenes' => $scenes,
                'summary' => [
                    'area_square_meters' => $areaSqm,
                    'area_hectares' => $areaHa,
                    'clip_cost' => $clipCost,
                    'processing_cost' => $processingCost,
                    'total_cost' => round($clipCost + $processingCost, 2),
                ],
            ],
        ]);
    }

    public function auto(Request $request): JsonResponse
    {
        $response = $this->search($request);
        $payload = $response->getData(true);

        if (($payload['success'] ?? false) === false) {
            return $response;
        }

        $scenes = $payload['data']['scenes'] ?? [];
        if (empty($scenes)) {
            return response()->json([
                'success' => false,
                'message' => 'No Sentinel-2 scenes matched the selected filters.',
                'data' => $payload['data'],
            ], 404);
        }

        $best = $this->selectBestScene($scenes);

        return response()->json([
            'success' => true,
            'data' => [
                'best_scene' => $best,
                'scenes' => $scenes,
                'summary' => $payload['data']['summary'] ?? [],
            ],
        ]);
    }

    public function process(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'field_name' => ['required', 'string', 'max:255'],
            'geometry' => ['required'],
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after_or_equal:start_date'],
            'max_cloud' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'mode' => ['required', 'in:auto,manual'],
            'scene' => ['required', 'array'],
            'scene.id' => ['nullable', 'string', 'max:255'],
            'scene.product_id' => ['nullable', 'string', 'max:255'],
            'scene.title' => ['nullable', 'string', 'max:255'],
            'scene.collection' => ['nullable', 'string', 'max:255'],
            'scene.acquisition_date' => ['nullable', 'string', 'max:255'],
            'scene.cloud_cover' => ['nullable', 'numeric'],
        ]);

        try {
            $geometry = GeometryHelper::normalizeGeometry($validated['geometry']);
        } catch (InvalidArgumentException $exception) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid geometry supplied.',
                'error' => $exception->getMessage(),
            ], 422);
        }

        $user = $request->user();
        $start = Carbon::parse($validated['start_date'])->startOfDay()->utc();
        $end = Carbon::parse($validated['end_date'])->endOfDay()->utc();
        $maxCloud = $validated['max_cloud'] ?? null;

        $areaSqm = GeometryHelper::calculateAreaSquareMeters($geometry);
        $areaHa = $areaSqm / 10000;
        if ($areaHa <= 0) {
            return response()->json([
                'success' => false,
                'message' => 'The drawn area is too small to process.',
            ], 422);
        }

        $clipCostPerHa = config('app-constants.imagery_credit_cost_per_hectare', 1);
        $clipCost = round($areaHa * $clipCostPerHa, 2);
        $processingCost = config('app-constants.imagery_processing_cost', 10);
        $totalCost = round($clipCost + $processingCost, 2);

        if (!$this->creditService->deductCreditsForProcessing((string) $user->id, $totalCost, 'SentinelClip')) {
            return response()->json([
                'success' => false,
                'message' => 'Insufficient credit points to process the selected area.',
            ], 422);
        }

        try {
            $fieldArea = FieldArea::create([
                'user_id' => $user->id,
                'name' => $validated['field_name'],
                'area_ha' => round($areaHa, 4),
                'geom' => $geometry,
            ]);

            $displayBase = FilenameHelper::sanitizeDisplayName($validated['field_name'] . '_' . now()->format('YmdHis'));
            $imageryDirectory = 'imagery';
            $outputFilename = FilenameHelper::ensureUniqueFilename($imageryDirectory, $displayBase . '_clip', 'tif');
            $originalName = $displayBase . '_clip.tif';

            $imagery = ImageryData::create([
                'user_id' => $user->id,
                'source_type' => 'sentinel-2',
                'original_name' => $originalName,
                'stored_name' => $outputFilename,
                'size' => 0,
                'format' => 'tif',
                'path' => 'storage/' . trim($imageryDirectory, '/') . '/' . $outputFilename,
                'upload_status' => 'uploading',
                'processing_status' => 'waiting',
                'uploaded_at' => now(),
            ]);

            $metadata = [
                'clip_mode' => true,
                'clip_geojson' => $geometry,
                'clip_field_area_id' => $fieldArea->id,
                'clip_date_from' => $start->toIso8601String(),
                'clip_date_to' => $end->toIso8601String(),
                'clip_max_cloud' => $maxCloud,
                'clip_limit' => 20,
                'clip_resolution' => 10,
                'clip_scene_id' => Arr::get($validated, 'scene.id'),
                'clip_scene_product_id' => Arr::get($validated, 'scene.product_id'),
                'clip_scene_title' => Arr::get($validated, 'scene.title'),
                'clip_scene_collection' => Arr::get($validated, 'scene.collection'),
                'clip_scene_acquisition' => Arr::get($validated, 'scene.acquisition_date'),
                'clip_scene_cloud' => Arr::get($validated, 'scene.cloud_cover'),
                'clip_total_cost' => $totalCost,
                'clip_area_cost' => $clipCost,
                'clip_processing_cost' => $processingCost,
            ];

            ProcessSentinelSceneJob::dispatch(
                $imagery->id,
                '',
                '',
                $outputFilename,
                $outputFilename,
                $originalName,
                $metadata
            )->onQueue('download');

            $remainingCredits = $this->creditService->getRemainingCredits($user->id);

            return response()->json([
                'success' => true,
                'message' => 'Sentinel-2 clipping task queued successfully.',
                'data' => [
                    'field_area_id' => $fieldArea->id,
                    'imagery_id' => $imagery->id,
                    'credits' => [
                        'total' => $totalCost,
                        'clip' => $clipCost,
                        'processing' => $processingCost,
                        'remaining' => $remainingCredits,
                    ],
                ],
            ], 202);
        } catch (Throwable $exception) {
            Log::error('SentinelClipController@process failed.', [
                'error' => $exception->getMessage(),
            ]);

            $this->creditService->addCreditsToUser((string) $user->id, $totalCost, 'SentinelClip:rollback');

            return response()->json([
                'success' => false,
                'message' => 'Unable to queue Sentinel-2 clipping at this time.',
            ], 500);
        }
    }

    protected function performSearch(array $geometry, Carbon $start, Carbon $end, int $limit, ?float $maxCloud, string $productType): array
    {
        $wkt = GeometryHelper::toWkt($geometry);

        $params = [
            'startDate' => $start->toIso8601String(),
            'completionDate' => $end->toIso8601String(),
            'geometry' => $wkt,
            'maxRecords' => $limit,
            'productType' => $productType,
            'format' => 'json',
            'sortParam' => 'completionDate',
            'sortOrder' => 'descending',
        ];

        if ($maxCloud !== null) {
            $params['cloudCover'] = '[0,' . (int) round($maxCloud) . ']';
        }

        $token = CopernicusTokenService::getAccessToken();
        $headers = [
            'Accept' => 'application/json',
        ];

        if ($token) {
            $headers['Authorization'] = 'Bearer ' . $token;
        }

        $response = Http::withHeaders($headers)
            ->timeout(30)
            ->get('https://catalogue.dataspace.copernicus.eu/resto/api/collections/Sentinel2/search.json', $params);

        if ($response->failed()) {
            throw new InvalidArgumentException('Catalogue request failed with status ' . $response->status());
        }

        $payload = $response->json();
        $features = Arr::get($payload, 'features', []);

        $scenes = [];
        foreach ($features as $feature) {
            $scenes[] = $this->mapScene($feature);
        }

        return array_values(array_filter($scenes));
    }

    protected function mapScene(array $feature): ?array
    {
        $properties = $feature['properties'] ?? [];
        $id = $feature['id'] ?? Arr::get($properties, 'id');
        $title = Arr::get($properties, 'title') ?? Arr::get($properties, 'productIdentifier');
        $collection = Arr::get($properties, 'collection');
        $acquired = Arr::get($properties, 'completionDate') ?? Arr::get($properties, 'startDate');
        $cloud = Arr::get($properties, 'cloudCover') ?? Arr::get($properties, 'eo:cloudCover');

        $downloadUrl = $this->resolveDownloadUrl($feature);
        $downloadFilename = $this->buildDownloadName($title, $id);

        return [
            'id' => $id,
            'title' => $title,
            'collection' => $collection,
            'acquisition_date' => $acquired,
            'cloud_cover' => $cloud !== null ? (float) $cloud : null,
            'product_id' => Arr::get($properties, 'productIdentifier'),
            'download_url' => $downloadUrl,
            'download_filename' => $downloadFilename,
            'geometry' => $feature['geometry'] ?? null,
            'bbox' => $feature['bbox'] ?? Arr::get($properties, 'bbox'),
            'links' => $feature['links'] ?? [],
            'assets' => $feature['assets'] ?? [],
            'properties' => $properties,
        ];
    }

    protected function selectBestScene(array $scenes): array
    {
        usort($scenes, function ($a, $b) {
            $cloudA = $a['cloud_cover'] ?? 1000;
            $cloudB = $b['cloud_cover'] ?? 1000;
            if ($cloudA === $cloudB) {
                return strcmp((string) ($b['acquisition_date'] ?? ''), (string) ($a['acquisition_date'] ?? ''));
            }
            return $cloudA <=> $cloudB;
        });

        return $scenes[0];
    }

    protected function resolveDownloadUrl(array $feature): ?string
    {
        $properties = $feature['properties'] ?? [];
        $candidates = [];

        $register = static function (?string $url) use (&$candidates) {
            if ($url && filter_var($url, FILTER_VALIDATE_URL)) {
                $candidates[] = $url;
            }
        };

        $register(Arr::get($properties, 'downloadUrl'));
        $register(Arr::get($properties, 'productDownloadUrl'));
        $register(Arr::get($properties, 'services.download.url'));

        $links = $feature['links'] ?? [];
        foreach ($links as $link) {
            $rel = strtolower((string) ($link['rel'] ?? ''));
            if (in_array($rel, ['enclosure', 'download', 'data'], true)) {
                $register($link['href'] ?? null);
            }
        }

        if (!empty($candidates)) {
            return $candidates[0];
        }

        if (isset($feature['assets']) && is_array($feature['assets'])) {
            foreach ($feature['assets'] as $asset) {
                if (is_array($asset)) {
                    $register($asset['href'] ?? ($asset['url'] ?? null));
                } elseif (is_string($asset)) {
                    $register($asset);
                }
            }
        }

        return $candidates[0] ?? null;
    }

    protected function buildDownloadName(?string $primary, ?string $fallback): string
    {
        $base = trim((string) ($primary ?: $fallback ?: 'sentinel-2-scene'));
        $normalized = preg_replace('/[^A-Za-z0-9._-]+/', '_', $base);
        return $normalized !== '' ? $normalized : 'sentinel-2-scene';
    }
}
