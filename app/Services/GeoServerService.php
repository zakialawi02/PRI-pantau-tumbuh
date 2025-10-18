<?php

namespace App\Services;

use App\Models\ImageryData;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class GeoServerService
{
    protected string $restUrl;
    protected string $username;
    protected string $password;
    protected string $workspace;
    protected string $wmsUrl;
    protected string $defaultSrs;

    public function __construct()
    {
        $config = config('geoserver');
        $this->restUrl = rtrim($config['url'] ?? '', '/');
        $this->username = $config['username'] ?? '';
        $this->password = $config['password'] ?? '';
        $this->workspace = $config['workspace'] ?? 'default';
        $this->defaultSrs = $config['default_srs'] ?? 'EPSG:4326';

        $configuredWms = $config['wms_url'] ?? '';
        if ($configuredWms) {
            $this->wmsUrl = rtrim($configuredWms, '/');
        } elseif ($this->restUrl !== '') {
            $this->wmsUrl = rtrim(preg_replace('#/rest$#', '/wms', $this->restUrl), '/');
        } else {
            $this->wmsUrl = '';
        }
    }

    public function getWorkspace(): string
    {
        return $this->workspace;
    }

    public function getWmsUrl(): string
    {
        return $this->wmsUrl;
    }

    public function buildIdentifier(string $imageryId, string $suffix): string
    {
        $normalised = Str::of($imageryId)->replace('-', '_')->slug('_');
        return trim("imagery_{$normalised}_{$suffix}", '_');
    }

    public function publishImageryLayer(ImageryData $imagery, string $filePath, string $variant = 'source'): ?array
    {
        if (!is_file($filePath)) {
            Log::warning('GeoServerService: File does not exist for publishing.', [
                'imagery_id' => $imagery->id,
                'path' => $filePath,
            ]);
            return null;
        }

        $suffix = $variant === 'processed' ? 'processed' : 'source';
        $storeName = $this->buildIdentifier($imagery->id, $suffix . '_store');
        $layerName = $this->buildIdentifier($imagery->id, $suffix . '_layer');

        $this->deleteLayer($layerName);
        $this->deleteCoverageStore($storeName);

        if (!$this->restUrl || !$this->workspace) {
            Log::warning('GeoServerService: Missing REST endpoint or workspace configuration.');
            return null;
        }

        $endpoint = sprintf(
            '%s/workspaces/%s/coveragestores/%s/external.geotiff',
            $this->restUrl,
            $this->workspace,
            $storeName
        );

        $query = http_build_query([
            'configure' => 'all',
            'coverageName' => $layerName,
        ]);

        $response = $this->client()
            ->withHeaders(['Content-Type' => 'text/plain'])
            ->send('PUT', $endpoint . '?' . $query, ['body' => 'file:' . $filePath]);

        if ($response->failed()) {
            Log::error('GeoServerService: Failed to publish GeoTIFF to GeoServer.', [
                'imagery_id' => $imagery->id,
                'status' => $response->status(),
                'body' => $response->body(),
            ]);
            return null;
        }

        $qualifiedName = $this->workspace . ':' . $layerName;

        $coverageConfig = $this->fetchCoverageConfig($storeName, $layerName);

        $detectedDeclaredSrs = $coverageConfig['srs'] ?? null;
        $detectedNativeCrs = $coverageConfig['nativeCRS'] ?? null;

        $declaredSrs = $detectedDeclaredSrs ?: $this->defaultSrs;
        $nativeCrs = $detectedNativeCrs ?: $declaredSrs;

        $supportedSrs = $this->buildSupportedSrsList($declaredSrs, $nativeCrs);
        if (empty($supportedSrs)) {
            $supportedSrs = [$declaredSrs];
        }

        $coverageResponse = $this->client()->put(
            sprintf(
                '%s/workspaces/%s/coveragestores/%s/coverages/%s',
                $this->restUrl,
                $this->workspace,
                $storeName,
                $layerName
            ),
            [
                'coverage' => [
                    'srs' => $declaredSrs,
                    'nativeCRS' => $nativeCrs,
                    'requestSRS' => ['string' => $supportedSrs],
                    'responseSRS' => ['string' => $supportedSrs],
                    'projectionPolicy' => 'NONE',
                    'enabled' => true,
                ],
            ]
        );

        if ($coverageResponse->failed()) {
            Log::warning('GeoServerService: Unable to configure coverage SRS.', [
                'imagery_id' => $imagery->id,
                'status' => $coverageResponse->status(),
                'body' => $coverageResponse->body(),
            ]);
        }

        $layerResponse = $this->client()->put(
            sprintf('%s/layers/%s', $this->restUrl, $qualifiedName),
            [
                'layer' => [
                    'enabled' => true,
                    'advertised' => true,
                    'type' => 'RASTER',
                    'projectionPolicy' => 'NONE',
                    'srs' => $declaredSrs,
                ],
            ]
        );

        if ($layerResponse->failed()) {
            Log::warning('GeoServerService: Unable to enable published layer.', [
                'imagery_id' => $imagery->id,
                'status' => $layerResponse->status(),
                'body' => $layerResponse->body(),
            ]);
        }

        $bounds = $this->fetchCoverageBounds($storeName, $layerName);

        return [
            'store' => $storeName,
            'layer' => $layerName,
            'qualified' => $qualifiedName,
            'wms_url' => $this->wmsUrl,
            'bounds' => $bounds,
        ];
    }

    public function removeImageryPublication(?string $layerName, ?string $storeName): void
    {
        if (!$layerName && !$storeName) {
            return;
        }

        try {
            if ($layerName) {
                $this->deleteLayer($layerName);
            }

            if ($storeName) {
                $this->deleteCoverageStore($storeName);
            }
        } catch (\Throwable $exception) {
            Log::warning('GeoServerService: Failed to remove imagery publication.', [
                'layer' => $layerName,
                'store' => $storeName,
                'error' => $exception->getMessage(),
            ]);
        }
    }

    public function getCoverageBounds(?string $storeName, ?string $layerName): ?array
    {
        if (!$storeName || !$layerName) {
            return null;
        }

        return $this->fetchCoverageBounds($storeName, $layerName);
    }

    protected function deleteLayer(string $layerName): void
    {
        if (!$layerName || !$this->restUrl) {
            return;
        }

        $qualified = $this->workspace . ':' . $layerName;
        $url = sprintf('%s/layers/%s?recurse=true', $this->restUrl, $qualified);
        $this->client()->delete($url);
    }

    protected function deleteCoverageStore(string $storeName): void
    {
        if (!$storeName || !$this->restUrl || !$this->workspace) {
            return;
        }

        $url = sprintf(
            '%s/workspaces/%s/coveragestores/%s?recurse=true',
            $this->restUrl,
            $this->workspace,
            $storeName
        );

        $this->client()->delete($url);
    }

    protected function client()
    {
        return Http::withOptions([
            'http_errors' => false,
            'timeout' => 60,
        ])->withBasicAuth($this->username, $this->password);
    }

    protected function fetchCoverageConfig(string $storeName, string $layerName): ?array
    {
        if (!$this->restUrl || !$this->workspace) {
            return null;
        }

        $url = sprintf(
            '%s/workspaces/%s/coveragestores/%s/coverages/%s.json',
            $this->restUrl,
            $this->workspace,
            $storeName,
            $layerName
        );

        $response = $this->client()->get($url);

        if ($response->failed()) {
            Log::debug('GeoServerService: Failed to fetch coverage configuration.', [
                'store' => $storeName,
                'layer' => $layerName,
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            return null;
        }

        $coverage = $response->json('coverage');

        return is_array($coverage) ? $coverage : null;
    }

    protected function fetchCoverageBounds(string $storeName, string $layerName): ?array
    {
        $coverage = $this->fetchCoverageConfig($storeName, $layerName);

        if (!$coverage) {
            return null;
        }

        $native = $this->normaliseBoundingBox(
            $coverage['nativeBoundingBox'] ?? null,
            $coverage['srs'] ?? null
        );

        $wgs84 = $this->normaliseBoundingBox(
            $coverage['latLonBoundingBox'] ?? null,
            'EPSG:4326'
        );

        if (!$native && !$wgs84) {
            return null;
        }

        return array_filter([
            'native' => $native,
            'wgs84' => $wgs84,
        ]);
    }

    protected function buildSupportedSrsList(string $declaredSrs, ?string $nativeCrs = null): array
    {
        $candidates = [$declaredSrs, $nativeCrs, $this->defaultSrs];
        $valid = [];

        foreach ($candidates as $candidate) {
            if (!is_string($candidate)) {
                continue;
            }

            $candidate = trim($candidate);

            if ($candidate === '') {
                continue;
            }

            if (!$this->isCodeSrs($candidate)) {
                continue;
            }

            $valid[] = $candidate;
        }

        return array_values(array_unique($valid));
    }

    protected function isCodeSrs(string $candidate): bool
    {
        if ($candidate === '') {
            return false;
        }

        if (preg_match('/^(?:urn:ogc:def:crs:[^:]+::\d+|[a-z0-9_]+:\d+)$/i', $candidate)) {
            return true;
        }

        return str_starts_with(strtoupper($candidate), 'EPSG:');
    }

    protected function normaliseBoundingBox($boundingBox, ?string $fallbackCrs = null): ?array
    {
        if (!is_array($boundingBox)) {
            return null;
        }

        $minx = $this->toFloat($boundingBox['minx'] ?? $boundingBox['west'] ?? null);
        $miny = $this->toFloat($boundingBox['miny'] ?? $boundingBox['south'] ?? null);
        $maxx = $this->toFloat($boundingBox['maxx'] ?? $boundingBox['east'] ?? null);
        $maxy = $this->toFloat($boundingBox['maxy'] ?? $boundingBox['north'] ?? null);

        if ($minx === null || $miny === null || $maxx === null || $maxy === null) {
            return null;
        }

        $projection = $boundingBox['crs'] ?? $boundingBox['srs'] ?? $fallbackCrs;

        return [
            'extent' => [$minx, $miny, $maxx, $maxy],
            'crs' => $projection,
            'projection' => $projection,
        ];
    }

    protected function toFloat($value): ?float
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (is_numeric($value)) {
            return (float) $value;
        }

        $filtered = filter_var($value, FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION | FILTER_FLAG_ALLOW_SCIENTIFIC);

        return is_numeric($filtered) ? (float) $filtered : null;
    }
}
