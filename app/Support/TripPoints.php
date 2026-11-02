<?php

namespace App\Support;

/**
 * Where a trip can start and end: the airports (config lankaguide.arrival_points) and every
 * district capital and tourist town (database/seeders/data/demo_towns.php), so travellers who are
 * already in Sri Lanka can start in Kandy and finish in Ella.
 */
final class TripPoints
{
    public const DEFAULT = 'BIA Katunayake';

    /** @var array<string, array{label: string, lat: float, lng: float, group: string}>|null */
    private static ?array $cache = null;

    /**
     * @return array<string, array{label: string, lat: float, lng: float, group: string}> keyed by name
     */
    public static function all(): array
    {
        if (self::$cache !== null) {
            return self::$cache;
        }

        $points = [];
        foreach (config('lankaguide.arrival_points') as $name => $point) {
            $points[$name] = ['label' => $point['label'], 'lat' => (float) $point['lat'], 'lng' => (float) $point['lng'], 'group' => 'Airports and Colombo'];
        }

        $towns = collect(require database_path('seeders/data/demo_towns.php'))->sortBy(0);
        foreach ($towns as [$town, , $lat, $lng]) {
            if (! isset($points[$town])) {
                $points[$town] = ['label' => $town, 'lat' => (float) $lat, 'lng' => (float) $lng, 'group' => 'Towns'];
            }
        }

        return self::$cache = $points;
    }

    /**
     * @return array{label: string, lat: float, lng: float, group: string}|null
     */
    public static function find(?string $name): ?array
    {
        return $name !== null ? (self::all()[$name] ?? null) : null;
    }

    /**
     * @return array<int, string>
     */
    public static function names(): array
    {
        return array_keys(self::all());
    }

    /**
     * Options grouped for a <select>: group => [name => label].
     *
     * @return array<string, array<string, string>>
     */
    public static function grouped(): array
    {
        $groups = [];
        foreach (self::all() as $name => $point) {
            $groups[$point['group']][$name] = $point['label'];
        }

        return $groups;
    }
}
