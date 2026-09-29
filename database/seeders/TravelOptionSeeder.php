<?php

namespace Database\Seeders;

use App\Models\Cuisine;
use App\Models\Guide;
use App\Models\Setting;
use App\Models\Vehicle;
use Illuminate\Database\Seeder;

/**
 * Vehicles, guides, cuisines and pricing settings (SRS 5.2, 5.4, 5.5, FR-11).
 */
class TravelOptionSeeder extends Seeder
{
    /** Cuisine preferences offered in builder step 6 (FR-11). */
    public const CUISINES = ['Sri Lankan', 'Western', 'Indian', 'Chinese', 'Vegetarian', 'Vegan', 'Halal', 'Seafood'];

    public function run(): void
    {
        foreach (require __DIR__.'/data/vehicles.php' as $tier => $vehicles) {
            foreach ($vehicles as [$type, $model, $minPax, $maxPax, $luggage, $dayRate, $kmRate]) {
                Vehicle::firstOrCreate(
                    ['tier' => $tier, 'type' => $type],
                    [
                        'example_model' => $model,
                        'min_pax' => $minPax,
                        'max_pax' => $maxPax,
                        'luggage_capacity' => $luggage,
                        'day_rate' => $dayRate,
                        'km_rate' => $kmRate,
                    ],
                );
            }
        }

        foreach (require __DIR__.'/data/guides.php' as [$name, $type, $languages, $dayRate]) {
            Guide::firstOrCreate(
                ['name' => $name],
                ['type' => $type, 'languages' => $languages, 'day_rate' => $dayRate],
            );
        }

        foreach (self::CUISINES as $name) {
            Cuisine::firstOrCreate(['name' => $name]);
        }

        foreach (require __DIR__.'/data/settings.php' as $key => $value) {
            Setting::firstOrCreate(['key' => $key], ['value' => $value]);
        }

        // Model events are muted while seeding, so clear the settings cache explicitly.
        Setting::flushCache();
    }
}
