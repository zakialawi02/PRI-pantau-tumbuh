<?php

namespace App\Support;

use InvalidArgumentException;

class GeometryHelper
{
    public static function normalizeGeometry(mixed $input): array
    {
        if (is_string($input)) {
            $decoded = json_decode($input, true);
            if (json_last_error() === JSON_ERROR_NONE) {
                return self::normalizeGeometry($decoded);
            }
        }

        if (is_array($input)) {
            if (isset($input['type'])) {
                $type = strtoupper((string) $input['type']);
                if ($type === 'FEATURECOLLECTION' && isset($input['features'][0]['geometry'])) {
                    return self::normalizeGeometry($input['features'][0]['geometry']);
                }
                if ($type === 'FEATURE' && isset($input['geometry'])) {
                    return self::normalizeGeometry($input['geometry']);
                }
                if (in_array($type, ['POLYGON', 'MULTIPOLYGON'], true)) {
                    return $input;
                }
            }

            if (isset($input['geometry'])) {
                return self::normalizeGeometry($input['geometry']);
            }
        }

        throw new InvalidArgumentException('Unsupported geometry input.');
    }

    public static function toWkt(array $geometry): string
    {
        $type = strtoupper((string) ($geometry['type'] ?? ''));
        $coordinates = $geometry['coordinates'] ?? null;

        if ($type === 'POLYGON') {
            return 'POLYGON(' . self::formatPolygonRings($coordinates) . ')';
        }

        if ($type === 'MULTIPOLYGON') {
            $parts = [];
            foreach ($coordinates as $polygon) {
                $parts[] = '(' . self::formatPolygonRings($polygon) . ')';
            }
            return 'MULTIPOLYGON(' . implode(',', $parts) . ')';
        }

        throw new InvalidArgumentException('Only Polygon and MultiPolygon geometries are supported.');
    }

    public static function calculateAreaSquareMeters(array $geometry): float
    {
        $type = strtoupper((string) ($geometry['type'] ?? ''));
        $coordinates = $geometry['coordinates'] ?? null;

        if ($type === 'POLYGON') {
            return self::areaForPolygon($coordinates);
        }

        if ($type === 'MULTIPOLYGON') {
            $total = 0.0;
            foreach ($coordinates as $polygon) {
                $total += self::areaForPolygon($polygon);
            }
            return $total;
        }

        throw new InvalidArgumentException('Only Polygon and MultiPolygon geometries are supported.');
    }

    protected static function formatPolygonRings(array $rings): string
    {
        if (!is_array($rings) || empty($rings)) {
            throw new InvalidArgumentException('Polygon coordinates are invalid.');
        }

        $formattedRings = [];
        foreach ($rings as $ring) {
            $points = [];
            foreach (self::ensureClosedRing($ring) as $point) {
                if (!is_array($point) || count($point) < 2) {
                    continue;
                }
                $points[] = sprintf('%.6f %.6f', $point[0], $point[1]);
            }
            $formattedRings[] = '(' . implode(', ', $points) . ')';
        }

        return implode(',', $formattedRings);
    }

    protected static function areaForPolygon(array $polygon): float
    {
        if (!is_array($polygon) || empty($polygon)) {
            return 0.0;
        }

        $outerRing = self::ensureClosedRing($polygon[0] ?? []);
        $outerArea = self::shoelaceArea($outerRing);

        $holeArea = 0.0;
        if (count($polygon) > 1) {
            for ($i = 1; $i < count($polygon); $i++) {
                $holeArea += self::shoelaceArea(self::ensureClosedRing($polygon[$i] ?? []));
            }
        }

        return max($outerArea - $holeArea, 0.0);
    }

    protected static function shoelaceArea(array $ring): float
    {
        $count = count($ring);
        if ($count < 3) {
            return 0.0;
        }

        $area = 0.0;
        for ($i = 0; $i < $count - 1; $i++) {
            [$x1, $y1] = self::projectToMeters($ring[$i]);
            [$x2, $y2] = self::projectToMeters($ring[$i + 1]);
            $area += ($x1 * $y2) - ($x2 * $y1);
        }

        return abs($area) / 2.0;
    }

    protected static function projectToMeters(array $point): array
    {
        if (count($point) < 2) {
            return [0.0, 0.0];
        }

        [$lon, $lat] = [$point[0], $point[1]];
        $earthRadius = 6378137.0;
        $x = deg2rad($lon) * $earthRadius;
        $latRad = deg2rad(max(min($lat, 89.9999), -89.9999));
        $y = $earthRadius * log(tan(pi() / 4 + $latRad / 2));

        return [$x, $y];
    }

    protected static function ensureClosedRing(array $ring): array
    {
        if (empty($ring)) {
            return $ring;
        }

        $first = $ring[0];
        $last = $ring[count($ring) - 1];
        if ($first[0] !== $last[0] || $first[1] !== $last[1]) {
            $ring[] = $first;
        }

        return $ring;
    }
}
