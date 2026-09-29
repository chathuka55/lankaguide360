<?php

namespace Database\Seeders;

use App\Models\District;
use App\Models\Province;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * 9 provinces and 25 districts (data/locations.php).
 */
class LocationSeeder extends Seeder
{
    public function run(): void
    {
        foreach (require __DIR__.'/data/locations.php' as $provinceName => $districts) {
            $province = Province::firstOrCreate(
                ['slug' => Str::slug($provinceName)],
                ['name' => $provinceName.' Province'],
            );

            foreach ($districts as $district) {
                District::firstOrCreate(
                    ['slug' => Str::slug($district['name'])],
                    [
                        'province_id' => $province->id,
                        'name' => $district['name'],
                        'lat' => $district['lat'],
                        'lng' => $district['lng'],
                    ],
                );
            }
        }
    }
}
