<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use RuntimeException;

class GeoServerService
{
    protected string $restUrl;

    protected string $rootUrl;

    protected string $username;

    protected string $password;

    protected string $workspace;

    protected string $defaultSrs;

    public function __construct(?array $config = null)
    {
        $config = $config ?? config('geoserver', []);

        $restUrl = rtrim((string) ($config['url'] ?? ''), '/');
        $this->restUrl = $restUrl;
        $this->rootUrl = rtrim(preg_replace('#/rest$#i', '', $restUrl) ?: $restUrl, '/');
        $this->username = (string) ($config['username'] ?? '');
        $this->password = (string) ($config['password'] ?? '');
        $this->workspace = (string) ($config['workspace'] ?? '');
        $this->defaultSrs = (string) ($config['default_srs'] ?? 'EPSG:4326');
    }

    public function getWorkspace(): string
    {
        return $this->workspace;
    }

    public function getWmsUrl(): string
    {
        $configured = (string) ($this->getConfigValue('wms_url') ?? '');
        if ($configured !== '') {
            return rtrim($configured, '/');
        }

        return $this->rootUrl !== '' ? $this->rootUrl . '/wms' : '';
    }

    public function getWmtsUrl(): string
    {
        $configured = (string) ($this->getConfigValue('wmts_url') ?? '');
        if ($configured !== '') {
            return rtrim($configured, '/');
        }

        return $this->rootUrl !== '' ? $this->rootUrl . '/gwc/service/wmts' : '';
    }

    public function qualifyLayerName(string $layerName): string
    {
        $layerName = $this->sanitizeName($layerName);

        if ($this->workspace === '') {
            return $layerName;
        }

        if (str_contains($layerName, ':')) {
            return $layerName;
        }

        return $this->workspace . ':' . $layerName;
    }

    public function publishGeoTiff(string $storeName, string $layerName, string $filePath, array $options = []): array
    {
        $storeName = $this->sanitizeName($storeName);
        $layerName = $this->sanitizeName($layerName);
        $fileReference = $options['file_reference'] ?? $this->formatFileReference($filePath);

        $query = array_filter([
            'configure' => $options['configure'] ?? 'all',
            'coverageName' => $layerName,
        ]);

        $response = $this->http()
            ->withHeaders(['Content-Type' => 'text/plain'])
            ->send('PUT', $this->buildUrl(
                "workspaces/{$this->workspace}/coveragestores/{$storeName}/external.geotiff"
            ), [
                'query' => $query,
                'body' => $fileReference,
            ]);

        if ($response->failed()) {
            throw new RuntimeException(
                sprintf(
                    'GeoServer coverage store publish failed (%s): %s',
                    $response->status(),
                    $response->body()
                )
            );
        }

        $coverage = $this->getCoverage($storeName, $layerName);

        $coverageOptions = $this->buildCoverageOptions($coverage, $options);
        $this->configureCoverage($storeName, $layerName, $coverageOptions);

        $layerOptions = $options;
        if (empty($layerOptions['default_style'])) {
            $layerOptions['default_style'] = 'raster';
        }

        $this->publishLayer($layerName, $layerOptions);

        $coverage = $this->getCoverage($storeName, $layerName);

        return [
            'store' => $storeName,
            'layer' => $this->qualifyLayerName($layerName),
            'coverage' => $coverage,
            'bounding_box' => $this->extractBoundingBoxes($coverage),
        ];
    }

    public function publishLayer(string $layerName, array $options = []): void
    {
        $qualifiedLayer = $this->qualifyLayerName($layerName);

        $payload = [
            'layer' => [
                'enabled' => $options['enabled'] ?? true,
            ],
        ];

        if (!empty($options['default_style'])) {
            $payload['layer']['defaultStyle'] = [
                'name' => $options['default_style'],
            ];
        }

        $response = $this->http()
            ->withHeaders(['Content-Type' => 'application/json'])
            ->put($this->buildUrl('layers/' . $qualifiedLayer . '.json'), $payload);

        if ($response->failed() && $response->status() !== 404) {
            throw new RuntimeException(
                sprintf(
                    'GeoServer layer publish failed (%s): %s',
                    $response->status(),
                    $response->body()
                )
            );
        }
    }

    public function deleteLayer(string $layerName, array $options = []): void
    {
        if ($layerName === '') {
            return;
        }

        $qualifiedLayer = $this->qualifyLayerName($layerName);

        $query = [];
        if (!empty($options['recurse']) || !empty($options['cascade'])) {
            $query['recurse'] = 'true';
        }

        if (!empty($options['purge'])) {
            $purge = $options['purge'];
            $query['purge'] = $purge === true ? 'all' : (string) $purge;
        }

        $response = $this->http()
            ->send('DELETE', $this->buildUrl('layers/' . $qualifiedLayer), [
                'query' => $query,
            ]);

        if ($response->failed() && $response->status() !== 404) {
            throw new RuntimeException(
                sprintf(
                    'GeoServer layer delete failed (%s): %s',
                    $response->status(),
                    $response->body()
                )
            );
        }
    }

    public function deleteCoverageStore(string $storeName, array $options = []): void
    {
        if ($storeName === '') {
            return;
        }

        $storeName = $this->sanitizeName($storeName);

        $query = [];
        if (!empty($options['recurse'])) {
            $query['recurse'] = 'true';
        }

        if (!empty($options['purge'])) {
            $purge = $options['purge'];
            $query['purge'] = $purge === true ? 'all' : (string) $purge;
        }

        $response = $this->http()
            ->send(
                'DELETE',
                $this->buildUrl("workspaces/{$this->workspace}/coveragestores/{$storeName}"),
                [
                    'query' => $query,
                ]
            );

        if ($response->failed() && $response->status() !== 404) {
            throw new RuntimeException(
                sprintf(
                    'GeoServer coverage store delete failed (%s): %s',
                    $response->status(),
                    $response->body()
                )
            );
        }
    }

    public function getCoverage(string $storeName, string $coverageName): ?array
    {
        $storeName = $this->sanitizeName($storeName);
        $coverageName = $this->sanitizeName($coverageName);

        $response = $this->http()
            ->get($this->buildUrl(
                "workspaces/{$this->workspace}/coveragestores/{$storeName}/coverages/{$coverageName}.json"
            ));

        if ($response->successful()) {
            return $response->json();
        }

        return null;
    }

    public function sanitizeName(string $name): string
    {
        $sanitized = Str::slug($name, '_');

        if ($sanitized === '') {
            return 'layer_' . uniqid();
        }

        return $sanitized;
    }

    public function formatFileReference(string $filePath): string
    {
        $path = realpath($filePath) ?: $filePath;

        if (str_starts_with($path, 'file:')) {
            return $path;
        }

        if (!str_starts_with($path, '/')) {
            $path = '/' . ltrim($path, '/');
        }

        return 'file://' . $path;
    }

    protected function extractBoundingBoxes(?array $coverage): array
    {
        $coverageData = $coverage['coverage'] ?? [];

        return [
            'latLon' => $this->normaliseBoundingBox($coverageData['latLonBoundingBox'] ?? null),
            'native' => $this->normaliseBoundingBox($coverageData['nativeBoundingBox'] ?? null),
        ];
    }

    protected function configureCoverage(string $storeName, string $coverageName, array $options = []): void
    {
        $storeName = $this->sanitizeName($storeName);
        $coverageName = $this->sanitizeName($coverageName);

        $projectionPolicy = strtoupper((string) ($options['projection_policy'] ?? 'FORCE_DECLARED'));

        $coveragePayload = [
            'enabled' => $options['enabled'] ?? true,
        ];

        $srs = $options['srs'] ?? null;
        if (is_string($srs)) {
            $srs = trim($srs);
        }

        if (!empty($srs)) {
            $coveragePayload['srs'] = $srs;
        }

        $nativeSrs = $options['native_srs'] ?? null;
        if (is_string($nativeSrs)) {
            $nativeSrs = trim($nativeSrs);
        }

        if (!empty($nativeSrs)) {
            $coveragePayload['nativeCRS'] = $nativeSrs;
        }

        if ($projectionPolicy !== '') {
            $coveragePayload['projectionPolicy'] = $projectionPolicy;
        }

        if (!empty($options['supported_formats'])) {
            $coveragePayload['supportedFormats'] = array_values((array) $options['supported_formats']);
        }

        $payload = [
            'coverage' => array_filter(
                $coveragePayload,
                static fn ($value) => $value !== null && $value !== ''
            ),
        ];

        $query = [];
        $recalculate = $options['recalculate'] ?? 'nativebbox,latlonbbox';
        if (!empty($recalculate)) {
            $query['recalculate'] = $recalculate;
        }

        $response = $this->http()
            ->withHeaders(['Content-Type' => 'application/json'])
            ->send(
                'PUT',
                $this->buildUrl(
                    "workspaces/{$this->workspace}/coveragestores/{$storeName}/coverages/{$coverageName}.json"
                ),
                [
                    'query' => $query,
                    'json' => $payload,
                ]
            );

        if ($response->failed() && $response->status() !== 404) {
            throw new RuntimeException(
                sprintf(
                    'GeoServer coverage configuration failed (%s): %s',
                    $response->status(),
                    $response->body()
                )
            );
        }
    }

    protected function normaliseBoundingBox($bbox): ?array
    {
        if (!is_array($bbox)) {
            return null;
        }

        $requiredKeys = ['minx', 'miny', 'maxx', 'maxy'];
        foreach ($requiredKeys as $key) {
            if (!array_key_exists($key, $bbox)) {
                return null;
            }
        }

        return [
            'minx' => (float) $bbox['minx'],
            'miny' => (float) $bbox['miny'],
            'maxx' => (float) $bbox['maxx'],
            'maxy' => (float) $bbox['maxy'],
            'crs' => (string) ($bbox['crs'] ?? ''),
        ];
    }

    protected function http()
    {
        return Http::withBasicAuth($this->username, $this->password)
            ->acceptJson();
    }

    protected function buildUrl(string $path): string
    {
        $path = ltrim($path, '/');

        return $this->restUrl . '/' . $path;
    }

    protected function getConfigValue(string $key): mixed
    {
        return config('geoserver.' . $key);
    }

    protected function buildCoverageOptions(?array $coverage, array $options): array
    {
        $coverageOptions = $options;

        if (empty($coverageOptions['srs'])) {
            $detectedSrs = $this->extractCoverageSrs($coverage);
            if (!empty($detectedSrs)) {
                $coverageOptions['srs'] = $detectedSrs;
            } elseif (!empty($this->defaultSrs)) {
                $coverageOptions['srs'] = $this->defaultSrs;
            }
        }

        if (empty($coverageOptions['native_srs'])) {
            $native = $this->extractCoverageNativeCrs($coverage);
            if (!empty($native)) {
                $coverageOptions['native_srs'] = $native;
            } elseif (!empty($coverageOptions['srs'])) {
                $coverageOptions['native_srs'] = $coverageOptions['srs'];
            } elseif (!empty($this->defaultSrs)) {
                $coverageOptions['native_srs'] = $this->defaultSrs;
            }
        }

        if (empty($coverageOptions['projection_policy'])) {
            $coverageOptions['projection_policy'] = 'FORCE_DECLARED';
        }

        return $coverageOptions;
    }

    protected function extractCoverageSrs(?array $coverage): ?string
    {
        $coverageData = $coverage['coverage'] ?? [];

        $candidates = [
            $coverageData['srs'] ?? null,
            $coverageData['crs'] ?? null,
        ];

        foreach ($candidates as $candidate) {
            if (is_string($candidate)) {
                $candidate = trim($candidate);
            }

            if (!empty($candidate)) {
                return $candidate;
            }
        }

        return null;
    }

    protected function extractCoverageNativeCrs(?array $coverage): ?string
    {
        $coverageData = $coverage['coverage'] ?? [];

        $candidates = [
            $coverageData['nativeCRS'] ?? null,
            $coverageData['nativeCrs'] ?? null,
        ];

        foreach ($candidates as $candidate) {
            if (is_string($candidate)) {
                $candidate = trim($candidate);
            }

            if (!empty($candidate)) {
                return $candidate;
            }
        }

        return null;
    }
}
