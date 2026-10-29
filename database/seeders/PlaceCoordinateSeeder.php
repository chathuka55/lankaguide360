<?php

namespace Database\Seeders;

use App\Models\Place;
use Illuminate\Database\Seeder;

/**
 * Applies the checked coordinates in data/place_coordinates.php and marks them as checked, so the
 * Wikipedia importer leaves them alone. Idempotent; never touches a place an admin has checked.
 */
class PlaceCoordinateSeeder extends Seeder
{
    public function run(): void
    {
        foreach (require __DIR__.'/data/place_coordinates.php' as $slug => [$lat, $lng]) {
            $place = Place::where('slug', $slug)->first();

            if (! $place || $place->coordinates_checked_at !== null) {
                continue;
            }

            $place->forceFill(['lat' => $lat, 'lng' => $lng, 'coordinates_checked_at' => now()])->save();
        }
    }
}
