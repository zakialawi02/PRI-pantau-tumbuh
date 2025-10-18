<?php

namespace App\Services;

use App\Models\ImageryData;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Symfony\Component\Process\Process;

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

        $prepared = $this->prepareFileForPublishing($filePath);
        $publishPath = $prepared['path'];
        $temporaryFile = $prepared['temporary'];
        $targetSrs = $prepared['target_srs'];
        $requestSrs = $prepared['request_srs'];

        $response = null;

        try {
            $response = $this->client()
                ->withHeaders(['Content-Type' => 'text/plain'])
                ->send('PUT', $endpoint . '?' . $query, ['body' => 'file:' . $publishPath]);
        } finally {
            if ($temporaryFile && is_file($temporaryFile)) {
                @unlink($temporaryFile);
            }
        }

        if (!$response || $response->failed()) {
            Log::error('GeoServerService: Failed to publish GeoTIFF to GeoServer.', [
                'imagery_id' => $imagery->id,
                'status' => $response?->status(),
                'body' => $response?->body(),
            ]);
            return null;
        }

        $qualifiedName = $this->workspace . ':' . $layerName;

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
                    'srs' => $targetSrs,
                    'nativeCRS' => $targetSrs,
                    'requestSRS' => ['string' => $requestSrs],
                    'responseSRS' => ['string' => $requestSrs],
                    'projectionPolicy' => 'FORCE_DECLARED',
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
                    'projectionPolicy' => 'FORCE_DECLARED',
                    'srs' => $targetSrs,
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

    protected function fetchCoverageBounds(string $storeName, string $layerName): ?array
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
            Log::debug('GeoServerService: Failed to fetch coverage bounds.', [
                'store' => $storeName,
                'layer' => $layerName,
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            return null;
        }

        $coverage = $response->json('coverage');
        if (!is_array($coverage)) {
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

    protected function prepareFileForPublishing(string $filePath): array
    {
        $requestSrs = [$this->defaultSrs];

        if (!is_file($filePath)) {
            return [
                'path' => $filePath,
                'temporary' => null,
                'target_srs' => $this->defaultSrs,
                'request_srs' => $requestSrs,
                'detected_srs' => null,
                'reprojected' => false,
            ];
        }

        $detectedSrs = $this->detectSrsFromFile($filePath);

        if (!$detectedSrs) {
            return [
                'path' => $filePath,
                'temporary' => null,
                'target_srs' => $this->defaultSrs,
                'request_srs' => $requestSrs,
                'detected_srs' => null,
                'reprojected' => false,
            ];
        }

        $requestSrs = array_values(array_unique(array_filter([$this->defaultSrs, $detectedSrs])));

        if (strcasecmp($detectedSrs, $this->defaultSrs) === 0) {
            return [
                'path' => $filePath,
                'temporary' => null,
                'target_srs' => $this->defaultSrs,
                'request_srs' => $requestSrs,
                'detected_srs' => $detectedSrs,
                'reprojected' => false,
            ];
        }

        $reprojectedPath = $this->reprojectToDefaultSrs($filePath, $detectedSrs);

        if (!$reprojectedPath) {
            return [
                'path' => $filePath,
                'temporary' => null,
                'target_srs' => $detectedSrs,
                'request_srs' => $requestSrs,
                'detected_srs' => $detectedSrs,
                'reprojected' => false,
            ];
        }

        return [
            'path' => $reprojectedPath,
            'temporary' => $reprojectedPath,
            'target_srs' => $this->defaultSrs,
            'request_srs' => array_values(array_unique([$this->defaultSrs])),
            'detected_srs' => $detectedSrs,
            'reprojected' => true,
        ];
    }

    protected function detectSrsFromFile(string $filePath): ?string
    {
        $process = new Process(['gdalinfo', '-json', $filePath]);
        $process->run();

        if (!$process->isSuccessful()) {
            Log::warning('GeoServerService: Unable to inspect GeoTIFF SRS.', [
                'path' => $filePath,
                'error' => $process->getErrorOutput() ?: $process->getOutput(),
            ]);

            return null;
        }

        $info = json_decode($process->getOutput(), true);

        if (!is_array($info)) {
            return null;
        }

        $coordinateSystem = $info['coordinateSystem'] ?? null;

        if (is_array($coordinateSystem)) {
            $identifier = $coordinateSystem['id'] ?? $coordinateSystem['authorityCode'] ?? null;

            if (is_string($identifier) && Str::startsWith($identifier, 'EPSG:')) {
                return strtoupper($identifier);
            }

            if (is_array($identifier)) {
                $authority = $identifier['authority'] ?? null;
                $code = $identifier['code'] ?? null;

                if (strtoupper((string) $authority) === 'EPSG' && $code !== null) {
                    return 'EPSG:' . $code;
                }
            }

            $code = $coordinateSystem['code'] ?? $coordinateSystem['epsg'] ?? null;
            if ($code !== null) {
                return 'EPSG:' . $code;
            }

            if (isset($coordinateSystem['wkt']) && is_string($coordinateSystem['wkt'])) {
                if (preg_match('/AUTHORITY\["EPSG","(\d+)"\]/', $coordinateSystem['wkt'], $matches)) {
                    return 'EPSG:' . $matches[1];
                }
            }
        }

        if (isset($info['wkt']) && is_string($info['wkt'])) {
            if (preg_match('/AUTHORITY\["EPSG","(\d+)"\]/', $info['wkt'], $matches)) {
                return 'EPSG:' . $matches[1];
            }
        }

        return null;
    }

    protected function reprojectToDefaultSrs(string $filePath, ?string $sourceSrs): ?string
    {
        $temporary = tempnam(sys_get_temp_dir(), 'imagery_');

        if ($temporary === false) {
            return null;
        }

        @unlink($temporary);

        $temporaryFile = $temporary . '.tif';

        $command = ['gdalwarp', '-t_srs', $this->defaultSrs, '-overwrite'];

        if ($sourceSrs) {
            $command[] = '-s_srs';
            $command[] = $sourceSrs;
        }

        $command[] = $filePath;
        $command[] = $temporaryFile;

        $process = new Process($command);
        $process->run();

        if (!$process->isSuccessful()) {
            Log::warning('GeoServerService: Failed to reproject imagery to default SRS.', [
                'source' => $filePath,
                'temporary' => $temporaryFile,
                'error' => $process->getErrorOutput() ?: $process->getOutput(),
            ]);

            if (is_file($temporaryFile)) {
                @unlink($temporaryFile);
            }

            return null;
        }

        return $temporaryFile;
    }
}
