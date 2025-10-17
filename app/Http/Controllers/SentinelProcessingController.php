<?php

namespace App\Http\Controllers;

use App\Jobs\ProcessSentinelClipJob;
use App\Jobs\ProcessSentinelSceneJob;
use App\Models\ImageryData;
use App\Services\CreditService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Support\Carbon;
use Throwable;

class SentinelProcessingController extends Controller
{
    protected $creditService;

    public function __construct(CreditService $creditService)
    {
        $this->creditService = $creditService;
    }

    public function processScene(Request $request)
    {
        $user = $request->user();

        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'download_url' => ['required', 'url'],
            'product_id' => ['nullable', 'string', 'max:255'],
            'collection' => ['nullable', 'string', 'max:255'],
            'acquisition_date' => ['nullable', 'string', 'max:255'],
            'download_filename' => ['nullable', 'string', 'max:255'],
        ]);

        $requiredCredits = (float) config('app-constants.imagery_processing_cost', 10);
        $currentCredits = $this->creditService->getRemainingCredits($user->id);

        if ($requiredCredits > 0 && $currentCredits < $requiredCredits) {
            return response()->json([
                'success' => false,
                'message' => 'Insufficient credits to process Sentinel imagery.',
                'data' => [
                    'current_credits' => $currentCredits,
                    'required_credits' => $requiredCredits,
                ],
            ], 402);
        }

        $rawTitle = trim($validated['title'] ?? '') ?: now()->format('YmdHis') . '_Sentinel_Scene';
        $displayTitle = $this->sanitizeDisplayName($rawTitle . '_' . now()->format('YmdHis'));
        $finalDisplayName = $displayTitle . '.tif';

        $disk = Storage::disk('public');
        $imageryDirectory = 'imagery';
        $sentinelDirectory = 'imagery/download/sentinel';

        $disk->makeDirectory($imageryDirectory);
        $disk->makeDirectory($sentinelDirectory);
        $zipFilename = $this->ensureUniqueFilename($sentinelDirectory, $displayTitle, 'zip');
        $outputBase = Str::limit($displayTitle . '_multispectral', 160, '');
        $outputFilename = $this->ensureUniqueFilename($imageryDirectory, $outputBase, 'tif');

        $deductedCredits = false;

        try {
            $imagery = ImageryData::create([
                'user_id' => $user->id,
                'source_type' => 'sentinel-2',
                'original_name' => $finalDisplayName,
                'stored_name' => $outputFilename,
                'size' => 0,
                'format' => 'zip',
                'path' => 'storage/' . trim($imageryDirectory, '/') . '/' . $outputFilename,
                'upload_status' => 'uploading',
                'processing_status' => 'waiting',
                'uploaded_at' => now(),
            ]);

            if ($requiredCredits > 0) {
                $deductedCredits = $this->creditService->deductCreditsForProcessing($user->id, $requiredCredits);

                if (!$deductedCredits) {
                    $currentCredits = $this->creditService->getRemainingCredits($user->id);
                    $imagery->delete();

                    return response()->json([
                        'success' => false,
                        'message' => 'Insufficient credits to process Sentinel imagery.',
                        'data' => [
                            'current_credits' => $currentCredits,
                            'required_credits' => $requiredCredits,
                        ],
                    ], 402);
                }
            }

            ProcessSentinelSceneJob::dispatch(
                $imagery->id,
                $validated['download_url'],
                $sentinelDirectory,
                $zipFilename,
                $outputFilename,
                $finalDisplayName,
                [
                    'product_id' => $validated['product_id'] ?? null,
                    'collection' => $validated['collection'] ?? null,
                    'acquisition_date' => $validated['acquisition_date'] ?? null,
                    'download_filename' => $validated['download_filename'] ?? null,
                ]
            )->onQueue('download');

            $remainingCredits = $this->creditService->getRemainingCredits($user->id);

            return response()->json([
                'success' => true,
                'message' => 'Sentinel scene queued for processing.',
                'data' => [
                    'id' => $imagery->id,
                    'upload_status' => $imagery->upload_status,
                    'processing_status' => $imagery->processing_status,
                    'current_credits' => $remainingCredits,
                    'required_credits' => $requiredCredits,
                ],
            ], 202);
        } catch (Throwable $exception) {
            Log::error('SentinelProcessingController@store failed to queue processing.', [
                'error' => $exception->getMessage(),
                'trace' => $exception->getTraceAsString(),
            ]);

            if (isset($imagery)) {
                $imagery->update([
                    'upload_status' => 'failed',
                    'processing_status' => 'error',
                ]);
            }

            if ($deductedCredits && $requiredCredits > 0) {
                $this->creditService->addCreditsToUser($user->id, $requiredCredits, 'SentinelProcessingController');
            }

            return response()->json([
                'success' => false,
                'message' => 'Unable to queue Sentinel imagery processing at this time.',
                'data' => [
                    'current_credits' => $this->creditService->getRemainingCredits($user->id),
                    'required_credits' => $requiredCredits,
                ],
            ], 500);
        }
    }

    public function processClip(Request $request)
    {
        $user = $request->user();

        $validated = $request->validate([
            'field_name' => ['nullable', 'string', 'max:255'],
            'geometry' => ['required'],
            'start_date' => ['nullable', 'date'],
        ]);

        try {
            $geometry = $this->normaliseGeometry($validated['geometry']);
        } catch (\InvalidArgumentException $exception) {
            return response()->json([
                'success' => false,
                'message' => $exception->getMessage(),
            ], 422);
        }

        try {
            $areaSqm = $this->calculateGeometryAreaSqm($geometry);
        } catch (\InvalidArgumentException $exception) {
            return response()->json([
                'success' => false,
                'message' => $exception->getMessage(),
            ], 422);
        }

        if ($areaSqm <= 0) {
            return response()->json([
                'success' => false,
                'message' => 'Unable to calculate the area of the provided geometry.',
            ], 422);
        }

        $areaHa = $areaSqm / 10000;
        $creditRate = (float) config('app-constants.imagery_credit_cost_per_hectare', 0);
        $requiredCredits = $creditRate > 0 ? round($areaHa * $creditRate, 2) : 0.0;
        $currentCredits = $this->creditService->getRemainingCredits($user->id);

        if ($requiredCredits > 0 && $currentCredits < $requiredCredits) {
            return response()->json([
                'success' => false,
                'message' => 'Insufficient credits to process the Sentinel-2 clip.',
                'data' => [
                    'current_credits' => $currentCredits,
                    'required_credits' => $requiredCredits,
                ],
            ], 402);
        }

        $disk = Storage::disk('public');
        $imageryDirectory = 'imagery';
        $clipDirectory = 'imagery/clipped';

        $disk->makeDirectory($imageryDirectory);
        $disk->makeDirectory($clipDirectory);

        $baseName = $validated['field_name']
            ? $this->sanitizeDisplayName($validated['field_name'] . '_' . now()->format('YmdHis'))
            : $this->sanitizeDisplayName('SentinelClip_' . now()->format('YmdHis'));
        $baseName = Str::limit($baseName, 160, '');

        $outputFilename = $this->ensureUniqueFilename($clipDirectory, $baseName, 'tif');
        $finalDisplayName = $baseName . '.tif';

        $deductedCredits = false;

        try {
            $imagery = ImageryData::create([
                'user_id' => $user->id,
                'source_type' => 'sentinel-2-clip',
                'original_name' => $finalDisplayName,
                'stored_name' => $outputFilename,
                'size' => 0,
                'format' => 'tif',
                'path' => 'storage/' . trim($clipDirectory, '/') . '/' . $outputFilename,
                'upload_status' => 'uploading',
                'processing_status' => 'waiting',
                'uploaded_at' => now(),
            ]);

            if ($requiredCredits > 0) {
                $deductedCredits = $this->creditService->deductCreditsForProcessing($user->id, $requiredCredits, 'SentinelClip');

                if (!$deductedCredits) {
                    $imagery->delete();

                    return response()->json([
                        'success' => false,
                        'message' => 'Insufficient credits to process the Sentinel-2 clip.',
                        'data' => [
                            'current_credits' => $this->creditService->getRemainingCredits($user->id),
                            'required_credits' => $requiredCredits,
                        ],
                    ], 402);
                }
            }

            $startDate = $validated['start_date'] ?? null;
            if (!empty($startDate)) {
                try {
                    $startDate = Carbon::parse($startDate)->startOfDay()->toIso8601String();
                } catch (Throwable $exception) {
                    Log::warning('SentinelProcessingController@processClip received invalid start date.', [
                        'start_date' => $startDate,
                        'error' => $exception->getMessage(),
                    ]);
                    $startDate = null;
                }
            }

            ProcessSentinelClipJob::dispatch(
                $imagery->id,
                null,
                [
                    'geometry' => $geometry,
                    'area_sqm' => $areaSqm,
                    'area_hectares' => $areaHa,
                    'credit_cost' => $requiredCredits,
                    'output_filename' => $outputFilename,
                    'field_name' => $validated['field_name'] ?? null,
                    'start_date' => $startDate,
                    'limit' => 10,
                    'resolution' => 10,
                ]
            )->onQueue('processing');

            $remainingCredits = $this->creditService->getRemainingCredits($user->id);

            return response()->json([
                'success' => true,
                'message' => 'Sentinel-2 clip queued for processing.',
                'data' => [
                    'id' => $imagery->id,
                    'area_hectares' => $areaHa,
                    'estimated_credits' => $requiredCredits,
                    'current_credits' => $remainingCredits,
                ],
            ], 202);
        } catch (Throwable $exception) {
            Log::error('SentinelProcessingController@processClip failed to queue processing.', [
                'error' => $exception->getMessage(),
                'trace' => $exception->getTraceAsString(),
            ]);

            if (isset($imagery)) {
                $imagery->update([
                    'upload_status' => 'failed',
                    'processing_status' => 'error',
                ]);
            }

            if ($deductedCredits && $requiredCredits > 0) {
                $this->creditService->addCreditsToUser($user->id, $requiredCredits, 'SentinelClip');
            }

            return response()->json([
                'success' => false,
                'message' => 'Unable to queue the Sentinel-2 clip at this time.',
                'data' => [
                    'current_credits' => $this->creditService->getRemainingCredits($user->id),
                    'required_credits' => $requiredCredits,
                ],
            ], 500);
        }
    }

    private function sanitizeDisplayName(string $value): string
    {
        $cleaned = str_replace([
            ' ',
            '\\',
            '/',
            ':',
            "\"",
            '*',
            '?',
            '<',
            '>',
            '|',
            'SAFE',
            '.'
        ], ' ', $value);

        $normalized = trim(preg_replace('/\s+/', '', $cleaned) ?? '');
        $fallback = $normalized !== '' ? $normalized : 'Sentinel_Scene';

        return Str::limit($fallback, 120, '');
    }


    private function ensureUniqueFilename(string $directory, string $baseName, string $extension): string
    {
        $disk = Storage::disk('public');
        $cleanDirectory = trim($directory, '/');
        $extension = ltrim($extension, '.');

        $candidateBase = $baseName !== '' ? $baseName : 'sentinel-scene';
        $filename = sprintf('%s.%s', $candidateBase, $extension);
        $counter = 1;

        $pathPrefix = $cleanDirectory === '' ? '' : $cleanDirectory . '/';

        while ($disk->exists($pathPrefix . $filename)) {
            $filename = sprintf('%s-%d.%s', $candidateBase, $counter, $extension);
            $counter++;
        }

        return $filename;
    }

    private function normaliseGeometry($geometry): array
    {
        if (is_string($geometry)) {
            $decoded = json_decode($geometry, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new \InvalidArgumentException('Invalid geometry payload provided.');
            }
            $geometry = $decoded;
        }

        if (!is_array($geometry)) {
            throw new \InvalidArgumentException('Geometry payload must be an array or JSON string.');
        }

        $type = strtoupper($geometry['type'] ?? '');
        if ($type === 'FEATURE') {
            return $this->normaliseGeometry($geometry['geometry'] ?? []);
        }

        if ($type === 'FEATURECOLLECTION') {
            $features = $geometry['features'] ?? [];
            foreach ($features as $feature) {
                try {
                    return $this->normaliseGeometry($feature);
                } catch (\InvalidArgumentException $exception) {
                    continue;
                }
            }

            throw new \InvalidArgumentException('FeatureCollection does not contain a valid geometry.');
        }

        if (!isset($geometry['coordinates']) || !is_array($geometry['coordinates'])) {
            throw new \InvalidArgumentException('Geometry payload is missing coordinates.');
        }

        return [
            'type' => strtoupper($geometry['type'] ?? ''),
            'coordinates' => $geometry['coordinates'],
        ];
    }

    private function calculateGeometryAreaSqm(array $geometry): float
    {
        $type = strtoupper($geometry['type'] ?? '');
        $coordinates = $geometry['coordinates'] ?? [];

        return match ($type) {
            'POLYGON' => $this->calculatePolygonAreaSqm($coordinates),
            'MULTIPOLYGON' => $this->calculateMultiPolygonAreaSqm($coordinates),
            default => throw new \InvalidArgumentException('Unsupported geometry type for area calculation.'),
        };
    }

    private function calculatePolygonAreaSqm(array $rings): float
    {
        if (!is_array($rings) || count($rings) === 0) {
            return 0.0;
        }

        $outerRing = $rings[0] ?? [];
        $area = $this->calculateRingAreaSqm($outerRing);

        if ($area <= 0) {
            return 0.0;
        }

        $holeCount = count($rings) - 1;
        if ($holeCount <= 0) {
            return $area;
        }

        for ($index = 1; $index < count($rings); $index++) {
            $area -= abs($this->calculateRingAreaSqm($rings[$index] ?? []));
        }

        return max($area, 0.0);
    }

    private function calculateMultiPolygonAreaSqm(array $polygons): float
    {
        $area = 0.0;
        foreach ($polygons as $polygon) {
            $area += $this->calculatePolygonAreaSqm($polygon ?? []);
        }

        return $area;
    }

    private function calculateRingAreaSqm(array $ring): float
    {
        $points = [];
        foreach ($ring as $coords) {
            if (!is_array($coords) || count($coords) < 2) {
                continue;
            }

            $points[] = $this->projectLonLatToMeters((float) $coords[0], (float) $coords[1]);
        }

        $count = count($points);
        if ($count < 3) {
            return 0.0;
        }

        $first = $points[0];
        $last = $points[$count - 1];
        if ($first[0] !== $last[0] || $first[1] !== $last[1]) {
            $points[] = $first;
            $count++;
        }

        $area = 0.0;
        for ($index = 0; $index < $count - 1; $index++) {
            [$x1, $y1] = $points[$index];
            [$x2, $y2] = $points[$index + 1];
            $area += ($x1 * $y2) - ($x2 * $y1);
        }

        return abs($area) / 2.0;
    }

    private function projectLonLatToMeters(float $lon, float $lat): array
    {
        $radius = 6378137.0;
        $maxLat = 89.9;
        $lat = max(min($lat, $maxLat), -$maxLat);

        $x = deg2rad($lon) * $radius;
        $y = $radius * log(tan(M_PI / 4 + deg2rad($lat) / 2));

        return [$x, $y];
    }
}
