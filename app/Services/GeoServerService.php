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

        $preparation = $this->prepareRasterForPublication($filePath);
        $fileToPublish = $preparation['path'] ?? $filePath;

        if (!is_file($fileToPublish)) {
            Log::warning('GeoServerService: Prepared raster file not found for publishing.', [
                'imagery_id' => $imagery->id,
                'path' => $fileToPublish,
            ]);
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
            ->send('PUT', $endpoint . '?' . $query, ['body' => 'file:' . $fileToPublish]);

        if ($response->failed()) {
            Log::error('GeoServerService: Failed to publish GeoTIFF to GeoServer.', [
                'imagery_id' => $imagery->id,
                'status' => $response->status(),
                'body' => $response->body(),
            ]);
            return null;
        }

        $qualifiedName = $this->workspace . ':' . $layerName;

        $outputCrs = $this->normaliseSrs($preparation['output_crs'] ?? null);
        $inputCrs = $this->normaliseSrs($preparation['input_crs'] ?? null) ?? $outputCrs;

        if (!$outputCrs && $inputCrs) {
            $outputCrs = $inputCrs;
        }

        if (!$outputCrs) {
            $outputCrs = $this->defaultSrs;
        }

        if (!$inputCrs) {
            $inputCrs = $outputCrs;
        }

        $requestCrs = $this->uniqueSrsList([$outputCrs, $inputCrs, $this->defaultSrs]);

        $coveragePayload = [
            'srs' => $outputCrs,
            'nativeCRS' => $inputCrs ?: $outputCrs,
            'requestSRS' => ['string' => $requestCrs ?: [$outputCrs]],
            'responseSRS' => ['string' => $requestCrs ?: [$outputCrs]],
            'projectionPolicy' => 'REPROJECT_TO_DECLARED',
            'enabled' => true,
        ];

        $coverageResponse = $this->client()->put(
            sprintf(
                '%s/workspaces/%s/coveragestores/%s/coverages/%s',
                $this->restUrl,
                $this->workspace,
                $storeName,
                $layerName
            ),
            [
                'coverage' => $coveragePayload,
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
                    'projectionPolicy' => 'REPROJECT_TO_DECLARED',
                    'srs' => $outputCrs,
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

        if (!$bounds && isset($preparation['bounds']) && is_array($preparation['bounds'])) {
            $bounds = $this->normalisePreparationBounds($preparation['bounds'], $outputCrs);
        }

        return [
            'store' => $storeName,
            'layer' => $layerName,
            'qualified' => $qualifiedName,
            'wms_url' => $this->wmsUrl,
            'bounds' => $bounds,
            'preparation' => $preparation,
            'published_path' => $fileToPublish,
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

    protected function prepareRasterForPublication(string $filePath): array
    {
        $result = ['path' => $filePath];

        if (!is_file($filePath)) {
            return $result;
        }

        try {
            $scriptsBase = base_path('scripts');
            $pythonService = app(PythonService::class);
            $pythonPath = $pythonService->resolvePythonPath($scriptsBase);

            if (!$pythonPath) {
                Log::debug('GeoServerService: Python interpreter not available for raster preparation.');
                return $result;
            }

            $scriptPath = $scriptsBase . DIRECTORY_SEPARATOR . 'reproject_raster.py';

            if (!is_file($scriptPath)) {
                Log::debug('GeoServerService: Raster reprojection script not found.', [
                    'script' => $scriptPath,
                ]);
                return $result;
            }

            $targetPath = $this->buildReprojectedPath($filePath);

            $command = [$pythonPath, $scriptPath, '--input', $filePath, '--target', $this->defaultSrs];

            if ($targetPath !== $filePath) {
                $command[] = '--output';
                $command[] = $targetPath;
            }

            $command[] = '--overwrite';

            $process = new Process(
                $command,
                $scriptsBase,
                $pythonService->buildProcessEnvironment()
            );

            $process->setTimeout(600);
            $process->run();

            if ($stderr = trim($process->getErrorOutput())) {
                Log::debug('GeoServerService: Raster preparation stderr.', [
                    'stderr' => $stderr,
                ]);
            }

            if (!$process->isSuccessful()) {
                Log::warning('GeoServerService: Raster preparation failed.', [
                    'exit_code' => $process->getExitCode(),
                    'output' => trim($process->getOutput()),
                ]);
                return $result;
            }

            $output = trim($process->getOutput());
            $decoded = json_decode($output, true);

            if (!is_array($decoded)) {
                Log::warning('GeoServerService: Unable to decode raster preparation response.', [
                    'output' => $output,
                ]);
                return $result;
            }

            if (!empty($decoded['error'])) {
                Log::warning('GeoServerService: Raster preparation reported error.', [
                    'error' => $decoded['error'],
                ]);
                return $result;
            }

            $preparedPath = is_string($decoded['output_path'] ?? null)
                ? $decoded['output_path']
                : $filePath;

            $result['path'] = $preparedPath;
            $result['reprojected'] = (bool) ($decoded['reprojected'] ?? false);
            $result['input_crs'] = $this->normaliseSrs($decoded['input_crs'] ?? null);
            $result['output_crs'] = $this->normaliseSrs($decoded['output_crs'] ?? null);

            if (!empty($decoded['bounds']) && is_array($decoded['bounds'])) {
                $result['bounds'] = $decoded['bounds'];
            }

            if (!empty($result['reprojected'])) {
                Log::info('GeoServerService: Raster reprojected prior to publication.', [
                    'source' => $filePath,
                    'target' => $preparedPath,
                    'input_crs' => $result['input_crs'],
                    'output_crs' => $result['output_crs'],
                ]);
            }
        } catch (\Throwable $exception) {
            Log::warning('GeoServerService: Exception during raster preparation.', [
                'error' => $exception->getMessage(),
            ]);
        }

        return $result;
    }

    protected function buildReprojectedPath(string $filePath): string
    {
        $directory = dirname($filePath);
        $extension = pathinfo($filePath, PATHINFO_EXTENSION);
        $extension = $extension ? '.' . $extension : '.tif';
        $filename = pathinfo($filePath, PATHINFO_FILENAME);

        if (str_ends_with(strtolower($filename), '_epsg4326')) {
            return $directory . DIRECTORY_SEPARATOR . $filename . $extension;
        }

        return $directory . DIRECTORY_SEPARATOR . $filename . '_epsg4326' . $extension;
    }

    protected function normaliseSrs($srs): ?string
    {
        if (!is_string($srs)) {
            return null;
        }

        $trimmed = trim($srs);
        if ($trimmed === '') {
            return null;
        }

        if (preg_match('/^EPSG[:\s]?([0-9]+)$/i', $trimmed, $matches)) {
            return 'EPSG:' . $matches[1];
        }

        return strtoupper($trimmed);
    }

    protected function uniqueSrsList(array $values): array
    {
        $unique = [];

        foreach ($values as $value) {
            $normalised = $this->normaliseSrs($value);
            if ($normalised && !in_array($normalised, $unique, true)) {
                $unique[] = $normalised;
            }
        }

        return $unique;
    }

    protected function normalisePreparationBounds(array $bounds, ?string $fallbackProjection = null): ?array
    {
        $normalised = [];

        if (isset($bounds['native']) && is_array($bounds['native'])) {
            $native = $this->normalisePreparedExtent($bounds['native'], $fallbackProjection);
            if ($native) {
                $normalised['native'] = $native;
            }
        }

        if (isset($bounds['target']) && is_array($bounds['target'])) {
            $target = $this->normalisePreparedExtent($bounds['target'], $fallbackProjection);
            if ($target) {
                $normalised['wgs84'] = $target;
            }
        }

        return $normalised ?: null;
    }

    protected function normalisePreparedExtent(array $extent, ?string $projection = null): ?array
    {
        if (array_is_list($extent)) {
            $normalisedExtent = $this->normaliseExtentValues($extent);
        } else {
            $normalisedExtent = $this->normaliseExtentValues([
                $extent['minx'] ?? $extent['minX'] ?? null,
                $extent['miny'] ?? $extent['minY'] ?? null,
                $extent['maxx'] ?? $extent['maxX'] ?? null,
                $extent['maxy'] ?? $extent['maxY'] ?? null,
            ]);
        }

        if (!$normalisedExtent) {
            return null;
        }

        $projection = $this->normaliseSrs($extent['projection'] ?? $extent['crs'] ?? $projection);

        return array_filter([
            'extent' => $normalisedExtent,
            'projection' => $projection,
            'crs' => $projection,
        ]);
    }

    protected function normaliseExtentValues(array $values): ?array
    {
        if (count($values) !== 4) {
            return null;
        }

        $converted = [];

        foreach ($values as $value) {
            $converted[] = $this->toFloat($value);
        }

        if (in_array(null, $converted, true)) {
            return null;
        }

        return array_map(static fn ($number) => (float) $number, $converted);
    }
}
