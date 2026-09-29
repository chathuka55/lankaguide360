<?php

namespace App\Support;

use App\Models\Trip;
use App\Models\TripDay;
use App\Models\TripStop;

/**
 * Data for the `tripMap` Alpine component (resources/js/map.js): numbered stops and a path per day.
 */
class TripMapData
{
    /**
     * From a saved trip. Stored stops have no road geometry, so the path joins the stops.
     *
     * @return array<int, array<string, mixed>>
     */
    public static function fromTrip(Trip $trip): array
    {
        $n = 0;

        return $trip->tripDays->map(function (TripDay $day) use (&$n) {
            $stops = $day->stops
                ->filter(fn (TripStop $s) => $s->place?->lat !== null)
                ->map(function (TripStop $s) use (&$n) {
                    return [
                        'lat' => (float) $s->place->lat,
                        'lng' => (float) $s->place->lng,
                        'name' => $s->place->name,
                        'n' => ++$n,
                        'time' => $s->arrive_at ? substr($s->arrive_at, 0, 5) : null,
                    ];
                })->values()->all();

            return [
                'number' => $day->day_number,
                'title' => $day->title,
                'stops' => $stops,
                'path' => array_map(fn ($s) => [$s['lat'], $s['lng']], $stops),
            ];
        })->all();
    }

    /**
     * From a generated itinerary array (ItineraryService), using road geometry when present.
     *
     * @param  array<string, mixed>  $itinerary
     * @return array<int, array<string, mixed>>
     */
    public static function fromItinerary(array $itinerary): array
    {
        $n = 0;

        return collect($itinerary['days'] ?? [])->map(function (array $day) use (&$n) {
            $path = [];
            $stops = [];

            foreach ($day['stops'] ?? [] as $stop) {
                $path = [...$path, ...self::geometry($stop['geometry'] ?? null)];
                $path[] = [(float) $stop['lat'], (float) $stop['lng']];
                $stops[] = ['lat' => (float) $stop['lat'], 'lng' => (float) $stop['lng'], 'name' => $stop['name'], 'n' => ++$n, 'time' => $stop['arrive'] ?? null];
            }

            if (! empty($day['end_transfer'])) {
                $path = [...$path, ...self::geometry($day['end_transfer']['geometry'] ?? null)];
            }

            return ['number' => $day['number'], 'title' => $day['title'] ?? null, 'stops' => $stops, 'path' => $path];
        })->all();
    }

    /**
     * @return array<int, array{0: float, 1: float}>
     */
    private static function geometry(mixed $geometry): array
    {
        if (! is_array($geometry)) {
            return [];
        }

        return array_values(array_filter(array_map(
            fn ($point) => is_array($point) && count($point) >= 2 ? [(float) $point[0], (float) $point[1]] : null,
            $geometry,
        )));
    }
}
