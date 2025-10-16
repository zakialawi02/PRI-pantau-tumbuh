<?php

namespace App\Http\Controllers;

use App\Jobs\ProcessSentinelClipJob;
use App\Models\ImageryData;
use App\Services\CreditService;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class SentinelClipController extends Controller
{
    public function search(Request $request)
    {
        $validated = $request->validate([
            'geometry' => ['required', 'array'],
            'date_from' => ['required', 'date'],
            'date_to' => ['required', 'date', 'after_or_equal:date_from'],
            'max_cloud' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:50'],
            'product_level' => ['nullable', 'string'],
        ]);

        $geometry = $validated['geometry'];
        $wkt = $this->geometryToWkt($geometry);

        $limit = $validated['limit'] ?? 20;
        $maxCloud = $validated['max_cloud'] ?? 60;
        $productLevel = in_array($validated['product_level'] ?? 'S2MSI2A', ['S2MSI2A', 'S2MSI1C'], true)
            ? $validated['product_level']
            : 'S2MSI2A';

        $params = [
            'maxRecords' => $limit,
            'startDate' => date('Y-m-d\TH:i:s\Z', strtotime($validated['date_from'])),
            'completionDate' => date('Y-m-d\TH:i:s\Z', strtotime($validated['date_to'])),
            'productType' => $productLevel,
            'sortParam' => 'startDate',
            'sortOrder' => 'descending',
            'geometry' => $wkt,
        ];

        if ($maxCloud !== null) {
            $params['cloudCover'] = sprintf('[0,%d]', (int) $maxCloud);
        }

        $response = Http::get('https://catalogue.dataspace.copernicus.eu/resto/api/collections/Sentinel2/search.json', $params);

        if ($response->failed()) {
            Log::warning('Sentinel clip search failed.', [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Unable to fetch Sentinel-2 scenes. Please try again later.',
            ], $response->status());
        }

        $payload = $response->json();
        $features = Arr::get($payload, 'features', []);

        $scenes = collect($features)
            ->map(function ($feature) {
                $properties = $feature['properties'] ?? [];
                $services = $feature['services'] ?? [];
                $assets = $feature['assets'] ?? [];
                $thumbnail = Arr::get($services, 'thumbnail.href') ?? Arr::get($assets, 'thumbnail.href');
                $dataLink = Arr::get($services, 'download.href') ?? Arr::get($assets, 'data.href');

                return [
                    'id' => $feature['id'] ?? null,
                    'title' => $properties['title'] ?? $feature['id'] ?? 'Sentinel-2 Scene',
                    'product_id' => $properties['productIdentifier'] ?? null,
                    'collection' => $properties['collection'] ?? null,
                    'acquisition_date' => $properties['datetime'] ?? null,
                    'begin_position' => $properties['beginPosition'] ?? null,
                    'end_position' => $properties['endPosition'] ?? null,
                    'cloud_cover' => $properties['cloudCover'] ?? $properties['eo:cloud_cover'] ?? null,
                    'platform' => $properties['platformName'] ?? null,
                    'thumbnail' => $thumbnail,
                    'download_url' => $dataLink,
                    'footprint' => $properties['s3Path'] ?? null,
                    'raw' => $feature,
                ];
            })
            ->filter(fn ($item) => !empty($item['id']))
            ->values()
            ->all();

        return response()->json([
            'success' => true,
            'scenes' => $scenes,
        ]);
    }

    public function process(Request $request)
    {
        $user = $request->user();

        if (!$user) {
            abort(401);
        }

        $validated = $request->validate([
            'geometry' => ['required', 'array'],
            'area_square_meters' => ['required', 'numeric', 'min:1'],
            'mode' => ['required', 'in:auto,manual'],
            'date_from' => ['required', 'date'],
            'date_to' => ['required', 'date', 'after_or_equal:date_from'],
            'max_cloud' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:100'],
            'resolution' => ['nullable', 'integer', 'min:10', 'max:60'],
            'scene_id' => ['nullable', 'string'],
            'title' => ['nullable', 'string', 'max:120'],
        ]);

        if ($validated['mode'] === 'manual' && empty($validated['scene_id'])) {
            throw ValidationException::withMessages([
                'scene_id' => 'A Sentinel-2 scene must be selected in manual mode.',
            ]);
        }

        $areaHectares = $validated['area_square_meters'] / 10000;
        $rate = config('app-constants.imagery_credit_cost_per_hectare', 1);
        $creditCost = round($areaHectares * $rate, 2);

        $creditService = new CreditService();

        if (!$creditService->deductCreditsForProcessing($user->id, $creditCost, 'ClipRequest')) {
            return response()->json([
                'success' => false,
                'message' => 'Insufficient credits to queue this request.',
                'required_credits' => $creditCost,
                'current_credits' => $creditService->getRemainingCredits($user->id),
            ], 422);
        }

        $displayTitle = $validated['title'] ?: 'Sentinel Clip ' . now()->format('Ymd_His');
        $storedName = Str::slug($displayTitle . '-' . Str::random(6)) . '.tif';

        $imagery = ImageryData::create([
            'user_id' => $user->id,
            'source_type' => 'sentinel-2-clip',
            'original_name' => $displayTitle . '.tif',
            'stored_name' => $storedName,
            'size' => 0,
            'format' => 'tif',
            'path' => 'storage/imagery/' . $storedName,
            'upload_status' => 'waiting',
            'processing_status' => 'waiting',
            'uploaded_at' => now(),
        ]);

        $jobOptions = [
            'geometry' => $validated['geometry'],
            'date_from' => $validated['date_from'],
            'date_to' => $validated['date_to'],
            'max_cloud' => $validated['max_cloud'] ?? 60,
            'limit' => $validated['limit'] ?? 40,
            'resolution' => $validated['resolution'] ?? 10,
            'scene_id' => $validated['mode'] === 'manual' ? $validated['scene_id'] : null,
            'output_filename' => $storedName,
        ];

        ProcessSentinelClipJob::dispatch($imagery->id, $jobOptions)->onQueue('download');

        return response()->json([
            'success' => true,
            'message' => 'Imagery clip request queued successfully.',
            'imagery_id' => $imagery->id,
            'credit_cost' => $creditCost,
            'current_credits' => $creditService->getRemainingCredits($user->id),
        ], 202);
    }

    protected function geometryToWkt(array $geometry): string
    {
        $type = $geometry['type'] ?? null;
        $coordinates = $geometry['coordinates'] ?? null;

        if (!$type || !$coordinates) {
            throw ValidationException::withMessages([
                'geometry' => 'Invalid GeoJSON geometry provided.',
            ]);
        }

        if ($type === 'Polygon') {
            return $this->polygonToWkt($coordinates);
        }

        if ($type === 'MultiPolygon') {
            $polygons = array_map(fn ($polygon) => $this->polygonToWkt($polygon, false), $coordinates);
            return 'MULTIPOLYGON(' . implode(',', $polygons) . ')';
        }

        throw ValidationException::withMessages([
            'geometry' => 'Only Polygon and MultiPolygon geometries are supported.',
        ]);
    }

    protected function polygonToWkt(array $coordinates, bool $wrap = true): string
    {
        $rings = array_map(function ($ring) {
            $points = array_map(function ($point) {
                $lon = $point[0];
                $lat = $point[1];
                return sprintf('%F %F', $lon, $lat);
            }, $ring);

            if ($points[0] !== end($points)) {
                $points[] = $points[0];
            }

            return '(' . implode(',', $points) . ')';
        }, $coordinates);

        $body = implode(',', $rings);

        return $wrap ? 'POLYGON(' . $body . ')' : '(' . $body . ')';
    }
}

