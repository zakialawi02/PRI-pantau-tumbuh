<?php

namespace App\Services;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use RuntimeException;

class GeoServerService
{
    protected string $restUrl;

    protected string $serviceBaseUrl;

    protected string $username;

    protected string $password;

    protected string $defaultWorkspace;

    protected string $defaultSrs;

    protected int $timeout;

    public function __construct(?string $workspace = null)
    {
        $config = config('geoserver');

        $this->restUrl = rtrim($config['url'] ?? '', '/');
        $this->username = $config['username'] ?? 'admin';
        $this->password = $config['password'] ?? 'geoserver';
        $this->defaultWorkspace = $workspace ?? ($config['workspace'] ?? 'default');
        $this->defaultSrs = $config['default_srs'] ?? 'EPSG:4326';
        $this->timeout = max(5, (int) ($config['timeout'] ?? 30));

        $serviceBase = preg_replace('#/rest/?$#', '', $this->restUrl);
        $this->serviceBaseUrl = rtrim($serviceBase ?: $this->restUrl, '/');
    }

    /**
     * Register a GeoTIFF file as a GeoServer coverage and return published layer details.
     */
    public function publishGeoTiff(string $filePath, array $options = []): array
    {
        if (!is_file($filePath)) {
            throw new RuntimeException("GeoTIFF not found at path {$filePath}");
        }

        $workspace = $options['workspace'] ?? $this->defaultWorkspace;
        $storeName = $this->normaliseName($options['store'] ?? pathinfo($filePath, PATHINFO_FILENAME) ?: 'geotiff_store');
        $coverageName = $this->normaliseName($options['coverage'] ?? $storeName);
        $title = $options['title'] ?? Str::title(str_replace('_', ' ', $coverageName));
        $srs = $options['srs'] ?? $this->defaultSrs;

        $this->ensureWorkspaceExists($workspace);

        $this->uploadGeoTiff($workspace, $storeName, $coverageName, $filePath);

        $this->publishCoverage($workspace, $storeName, $coverageName, [
            'title' => $title,
            'srs' => $srs,
        ]);

        $coverageDetails = $this->fetchCoverage($workspace, $storeName, $coverageName);

        $layerName = sprintf('%s:%s', $workspace, $coverageName);

        return [
            'workspace' => $workspace,
            'store' => $storeName,
            'coverage' => $coverageName,
            'layer' => $layerName,
            'title' => $coverageDetails['title'] ?? $title,
            'srs' => $coverageDetails['srs'] ?? $srs,
            'bbox' => $coverageDetails['bbox'] ?? null,
            'wms' => [
                'url' => $this->buildWmsEndpoint($workspace),
                'layer' => $layerName,
            ],
            'wmts' => [
                'url' => $this->buildWmtsEndpoint(),
                'layer' => $layerName,
            ],
        ];
    }

    protected function client(): PendingRequest
    {
        return Http::withBasicAuth($this->username, $this->password)
            ->baseUrl($this->restUrl)
            ->timeout($this->timeout)
            ->acceptJson();
    }

    protected function normaliseName(string $value, string $fallback = 'layer'): string
    {
        $slug = Str::of($value)
            ->lower()
            ->replaceMatches('/[^a-z0-9]+/', '_')
            ->trim('_')
            ->value();

        if ($slug === '') {
            $slug = $fallback;
        }

        return substr($slug, 0, 64);
    }

    protected function ensureWorkspaceExists(string $workspace): void
    {
        $response = $this->client()->get("workspaces/{$workspace}.json");

        if ($response->successful()) {
            return;
        }

        if ($response->status() !== 404) {
            throw new RuntimeException('Failed to query GeoServer workspace: ' . $response->body());
        }

        $create = $this->client()->post('workspaces', [
            'workspace' => ['name' => $workspace],
        ]);

        if (!in_array($create->status(), [200, 201, 409], true)) {
            throw new RuntimeException('Unable to create GeoServer workspace: ' . $create->body());
        }
    }

    protected function uploadGeoTiff(string $workspace, string $store, string $coverage, string $filePath): void
    {
        $endpoint = "workspaces/{$workspace}/coveragestores/{$store}/file.geotiff";

        $response = $this->client()
            ->withOptions([
                'query' => [
                    'coverageName' => $coverage,
                    'configure' => 'none',
                    'update' => 'overwrite',
                ],
            ])
            ->withHeaders(['Content-Type' => 'image/tiff'])
            ->withBody(file_get_contents($filePath), 'image/tiff')
            ->send('PUT', $endpoint);

        if (!in_array($response->status(), [200, 201, 202], true)) {
            throw new RuntimeException('Failed to upload GeoTIFF to GeoServer: ' . $response->body());
        }
    }

    protected function publishCoverage(string $workspace, string $store, string $coverage, array $options = []): void
    {
        $payload = [
            'coverage' => [
                'name' => $coverage,
                'nativeName' => $coverage,
                'title' => $options['title'] ?? $coverage,
                'srs' => $options['srs'] ?? $this->defaultSrs,
                'enabled' => true,
            ],
        ];

        $endpoint = "workspaces/{$workspace}/coveragestores/{$store}/coverages";
        $response = $this->client()->post($endpoint, $payload);

        if (in_array($response->status(), [200, 201], true)) {
            return;
        }

        if ($response->status() === 409) {
            $updateEndpoint = "workspaces/{$workspace}/coveragestores/{$store}/coverages/{$coverage}";
            $update = $this->client()->put($updateEndpoint, $payload);

            if (!$update->successful()) {
                throw new RuntimeException('Failed to update GeoServer coverage: ' . $update->body());
            }

            return;
        }

        throw new RuntimeException('Failed to publish GeoServer coverage: ' . $response->body());
    }

    protected function fetchCoverage(string $workspace, string $store, string $coverage): array
    {
        $endpoint = "workspaces/{$workspace}/coveragestores/{$store}/coverages/{$coverage}.json";
        $response = $this->client()->get($endpoint);

        if (!$response->successful()) {
            Log::warning('GeoServerService: Unable to fetch coverage metadata', [
                'workspace' => $workspace,
                'store' => $store,
                'coverage' => $coverage,
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            return [];
        }

        $data = $response->json('coverage') ?? [];

        $bbox = $data['latLonBoundingBox'] ?? $data['nativeBoundingBox'] ?? null;
        $minX = isset($bbox['minx']) ? (float) $bbox['minx'] : (isset($bbox['minLon']) ? (float) $bbox['minLon'] : null);
        $minY = isset($bbox['miny']) ? (float) $bbox['miny'] : (isset($bbox['minLat']) ? (float) $bbox['minLat'] : null);
        $maxX = isset($bbox['maxx']) ? (float) $bbox['maxx'] : (isset($bbox['maxLon']) ? (float) $bbox['maxLon'] : null);
        $maxY = isset($bbox['maxy']) ? (float) $bbox['maxy'] : (isset($bbox['maxLat']) ? (float) $bbox['maxLat'] : null);

        $result = [
            'title' => $data['title'] ?? null,
            'srs' => $data['srs'] ?? ($data['nativeCRS'] ?? null),
        ];

        if ($minX !== null && $minY !== null && $maxX !== null && $maxY !== null) {
            $result['bbox'] = [$minX, $minY, $maxX, $maxY];
        }

        return $result;
    }

    protected function buildWmsEndpoint(string $workspace): string
    {
        return rtrim(sprintf('%s/%s/wms', $this->serviceBaseUrl, $workspace), '/');
    }

    protected function buildWmtsEndpoint(): string
    {
        return rtrim(sprintf('%s/gwc/service/wmts', $this->serviceBaseUrl), '/');
    }
}

