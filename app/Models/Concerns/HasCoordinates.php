<?php

namespace App\Models\Concerns;

use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Distance queries for models with lat/lng columns (haversine, kilometres).
 *
 * @mixin Model
 */
trait HasCoordinates
{
    /**
     * Rows within $km of a point, nearest first, with a `distance_km` column.
     */
    #[Scope]
    protected function nearTo(Builder $query, float $lat, float $lng, float $km): void
    {
        $table = $this->getTable();
        $distance = "(6371 * acos(least(1, cos(radians(?)) * cos(radians({$table}.lat)) * cos(radians({$table}.lng) - radians(?)) + sin(radians(?)) * sin(radians({$table}.lat)))))";

        $query->select("{$table}.*")
            ->selectRaw("{$distance} as distance_km", [$lat, $lng, $lat])
            ->whereNotNull("{$table}.lat")
            ->whereNotNull("{$table}.lng")
            // Cheap bounding box first so the index-free trig only runs on nearby rows.
            ->whereBetween("{$table}.lat", [$lat - $km / 111, $lat + $km / 111])
            ->whereBetween("{$table}.lng", [$lng - $km / 100, $lng + $km / 100])
            ->whereRaw("{$distance} <= ?", [$lat, $lng, $lat, $km])
            ->orderBy('distance_km');
    }
}
