<?php

namespace App\Support;

/**
 * Small geometry helpers for GeoJSON Polygon/MultiPolygon geometries.
 * Coordinates follow GeoJSON order: [lng, lat].
 */
final class Geo
{
    private const EARTH_RADIUS_KM = 6371.0;

    public static function distanceKm(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        $dLat = deg2rad($lat2 - $lat1);
        $dLng = deg2rad($lng2 - $lng1);
        $a = sin($dLat / 2) ** 2 + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLng / 2) ** 2;

        return 2 * self::EARTH_RADIUS_KM * asin(min(1.0, sqrt($a)));
    }

    /**
     * @param  array{type: string, coordinates: array}  $geometry
     * @return array<int, array<int, array<int, array{0: float, 1: float}>>> list of polygons, each a list of rings
     */
    public static function polygons(array $geometry): array
    {
        return match ($geometry['type'] ?? null) {
            'Polygon' => [$geometry['coordinates']],
            'MultiPolygon' => $geometry['coordinates'],
            default => [],
        };
    }

    /**
     * Area-weighted centroid of the outer rings.
     *
     * @return array{lat: float, lng: float}|null
     */
    public static function centroid(array $geometry): ?array
    {
        $sumArea = $sumX = $sumY = 0.0;

        foreach (self::polygons($geometry) as $rings) {
            $ring = $rings[0] ?? [];
            $area = 0.0;
            $cx = $cy = 0.0;

            for ($i = 0, $n = count($ring); $i < $n - 1; $i++) {
                [$x1, $y1] = $ring[$i];
                [$x2, $y2] = $ring[$i + 1];
                $cross = $x1 * $y2 - $x2 * $y1;
                $area += $cross;
                $cx += ($x1 + $x2) * $cross;
                $cy += ($y1 + $y2) * $cross;
            }

            if ($area == 0.0) {
                continue;
            }

            // Signed area is 2A; centroid = (cx, cy) / (3 * 2A). Weight by |A| across polygons.
            $sumX += $cx / 3;
            $sumY += $cy / 3;
            $sumArea += $area;
        }

        if ($sumArea == 0.0) {
            return null;
        }

        return ['lat' => $sumY / $sumArea, 'lng' => $sumX / $sumArea];
    }

    /**
     * @return array{south: float, west: float, north: float, east: float}|null
     */
    public static function bbox(array $geometry): ?array
    {
        $lngs = $lats = [];

        foreach (self::polygons($geometry) as $rings) {
            foreach ($rings[0] ?? [] as [$lng, $lat]) {
                $lngs[] = $lng;
                $lats[] = $lat;
            }
        }

        if ($lats === []) {
            return null;
        }

        return ['south' => min($lats), 'west' => min($lngs), 'north' => max($lats), 'east' => max($lngs)];
    }

    public static function contains(array $geometry, float $lat, float $lng): bool
    {
        foreach (self::polygons($geometry) as $rings) {
            if (! self::inRing($rings[0] ?? [], $lat, $lng)) {
                continue;
            }

            $inHole = false;
            foreach (array_slice($rings, 1) as $hole) {
                if (self::inRing($hole, $lat, $lng)) {
                    $inHole = true;
                    break;
                }
            }

            if (! $inHole) {
                return true;
            }
        }

        return false;
    }

    /**
     * Round every coordinate (5 decimals ≈ 1 m) to shrink GeoJSON files.
     */
    public static function roundGeometry(array $geometry, int $precision = 5): array
    {
        $round = function (array $coordinates) use (&$round, $precision): array {
            if (isset($coordinates[0]) && is_numeric($coordinates[0])) {
                return array_map(fn ($value) => round((float) $value, $precision), $coordinates);
            }

            return array_map($round, $coordinates);
        };

        $geometry['coordinates'] = $round($geometry['coordinates'] ?? []);

        return $geometry;
    }

    /**
     * Ray casting point-in-polygon test for one ring of [lng, lat] points.
     */
    private static function inRing(array $ring, float $lat, float $lng): bool
    {
        $inside = false;
        $n = count($ring);

        for ($i = 0, $j = $n - 1; $i < $n; $j = $i++) {
            [$xi, $yi] = $ring[$i];
            [$xj, $yj] = $ring[$j];

            if (($yi > $lat) !== ($yj > $lat) && $lng < ($xj - $xi) * ($lat - $yi) / (($yj - $yi) ?: 1e-12) + $xi) {
                $inside = ! $inside;
            }
        }

        return $inside;
    }
}
