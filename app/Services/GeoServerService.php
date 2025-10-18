<?php

namespace App\Services;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use RuntimeException;

class GeoServerService
{
    protected string $restBaseUrl;

    protected string $serviceBaseUrl;

    protected string $username;

    protected string $password;

    protected string $workspace;

    public function __construct()
    {
        $config = config('geoserver');

        $this->restBaseUrl = rtrim(Arr::get($config, 'url'), '/');
        $this->serviceBaseUrl = rtrim(preg_replace('#/rest$#', '', $this->restBaseUrl) ?? $this->restBaseUrl, '/');
        $this->username = Arr::get($config, 'username');
        $this->password = Arr::get($config, 'password');
        $this->workspace = Arr::get($config, 'workspace');
    }

    public function publishGeoTiff(string $layerName, string $filePath, ?string $storeName = null, ?string $workspace = null, array $coverageOptions = []): array
    {
        $workspace = $workspace ?: $this->workspace;
        $storeName = $storeName ?: $this->generateStoreName($layerName);

        if (! is_file($filePath)) {
            throw new RuntimeException(sprintf('GeoTIFF file not found at path: %s', $filePath));
        }

        $this->createCoverageStoreFromGeoTiff($storeName, $filePath, $workspace);
        $coverageResponse = $this->publishCoverageLayer($storeName, $layerName, $workspace, $coverageOptions);

        $coverageDetails = null;

        try {
            $coverageDetails = $this->extractCoverageDetails($coverageResponse);

            if (empty($coverageDetails)) {
                $coverageDetails = $this->getCoverageDetails($storeName, $layerName, $workspace);
            }
        } catch (\Throwable $exception) {
            Log::warning('Failed to retrieve GeoServer coverage details.', [
                'store' => $storeName,
                'layer' => $layerName,
                'workspace' => $workspace,
                'error' => $exception->getMessage(),
            ]);
        }

        $qualifiedLayerName = $this->getQualifiedLayerName($layerName, $workspace);

        return [
            'store' => $storeName,
            'workspace' => $workspace,
            'layer' => $qualifiedLayerName,
            'wms' => [
                'base_url' => $this->getWmsBaseUrl($workspace),
                'params' => array_merge([
                    'LAYERS' => $qualifiedLayerName,
                    'TILED' => true,
                ], $coverageOptions['wms_params'] ?? []),
            ],
            'wmts' => [
                'base_url' => $this->getWmtsBaseUrl(),
                'layer' => $qualifiedLayerName,
            ],
            'coverage' => $coverageDetails,
            'bbox' => [
                'native' => Arr::get($coverageDetails ?? [], 'nativeBoundingBox'),
                'latlon' => Arr::get($coverageDetails ?? [], 'latLonBoundingBox'),
            ],
        ];
    }

    public function createCoverageStoreFromGeoTiff(string $storeName, string $filePath, ?string $workspace = null): Response
    {
        $workspace = $workspace ?: $this->workspace;

        $endpoint = sprintf(
            '%s/workspaces/%s/coveragestores/%s/file.geotiff?configure=all',
            $this->restBaseUrl,
            $workspace,
            $storeName
        );

        $response = $this->newClient()
            ->withHeaders(['Content-Type' => 'image/tiff'])
            ->withBody(file_get_contents($filePath), 'image/tiff')
            ->put($endpoint);

        $this->throwIfRequestFailed($response, 'Failed to create GeoTIFF coverage store.');

        return $response;
    }

    public function publishCoverageLayer(string $storeName, string $layerName, ?string $workspace = null, array $options = []): Response
    {
        $workspace = $workspace ?: $this->workspace;

        $endpoint = sprintf(
            '%s/workspaces/%s/coveragestores/%s/coverages',
            $this->restBaseUrl,
            $workspace,
            $storeName
        );

        $payload = ['coverage' => $this->buildCoveragePayload($layerName, $options)];

        $response = $this->newClient()
            ->withHeaders(['Content-Type' => 'application/json'])
            ->post($endpoint, $payload);

        $this->throwIfRequestFailed($response, 'Failed to publish GeoTIFF layer.');

        return $response;
    }

    public function getQualifiedLayerName(string $layerName, ?string $workspace = null): string
    {
        $workspace = $workspace ?: $this->workspace;

        return sprintf('%s:%s', $workspace, $layerName);
    }

    public function getWmsBaseUrl(?string $workspace = null): string
    {
        $workspace = $workspace ?: $this->workspace;

        return sprintf('%s/%s/wms', $this->serviceBaseUrl, $workspace);
    }

    public function getWmtsBaseUrl(): string
    {
        return sprintf('%s/gwc/service/wmts', $this->serviceBaseUrl);
    }

    protected function newClient(): PendingRequest
    {
        return Http::withBasicAuth($this->username, $this->password)->acceptJson();
    }

    protected function throwIfRequestFailed(Response $response, string $message): void
    {
        if ($response->failed()) {
            $errorMessage = sprintf('%s Response: %s', $message, $response->body());
            Log::error($errorMessage);

            throw new RuntimeException($errorMessage, $response->status());
        }
    }

    protected function buildCoveragePayload(string $layerName, array $options = []): array
    {
        $coverage = [
            'name' => $layerName,
            'nativeName' => $options['nativeName'] ?? $layerName,
            'title' => $options['title'] ?? $layerName,
            'enabled' => $options['enabled'] ?? true,
        ];

        foreach (['srs', 'projectionPolicy', 'metadata', 'dimensions'] as $optionalKey) {
            if (array_key_exists($optionalKey, $options)) {
                $coverage[$optionalKey] = $options[$optionalKey];
            }
        }

        return $coverage;
    }

    protected function extractCoverageDetails(?Response $response): ?array
    {
        if (! $response instanceof Response) {
            return null;
        }

        $payload = $response->json();

        if (! is_array($payload)) {
            return null;
        }

        return Arr::get($payload, 'coverage');
    }

    public function getCoverageDetails(string $storeName, string $layerName, ?string $workspace = null): ?array
    {
        $workspace = $workspace ?: $this->workspace;

        $endpoint = sprintf(
            '%s/workspaces/%s/coveragestores/%s/coverages/%s.json',
            $this->restBaseUrl,
            $workspace,
            $storeName,
            $layerName
        );

        $response = $this->newClient()->get($endpoint);

        $this->throwIfRequestFailed($response, 'Failed to fetch coverage details.');

        return Arr::get($response->json(), 'coverage');
    }

    protected function generateStoreName(string $layerName): string
    {
        return Str::slug($layerName, '_');
    }
}
