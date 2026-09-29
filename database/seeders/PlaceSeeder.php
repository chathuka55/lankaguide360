<?php

namespace Database\Seeders;

use App\Enums\PublishStatus;
use App\Models\Category;
use App\Models\District;
use App\Models\Place;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Starter places (data/places.php), seeded as drafts for the importers and admin to complete.
 */
class PlaceSeeder extends Seeder
{
    /** Short keys used in data/places.php → category slugs. */
    public const CATEGORY_KEYS = [
        'beach' => 'beach-coastal',
        'history' => 'historical-cultural',
        'hills' => 'hill-country',
        'wildlife' => 'nature-wildlife',
        'hiking' => 'hiking-adventure',
        'gems' => 'hidden-gems',
    ];

    public function run(): void
    {
        $districtIds = District::pluck('id', 'slug');
        $categoryIds = Category::pluck('id', 'slug');

        foreach (require __DIR__.'/data/places.php' as [$name, $wikipediaTitle, $district, $categories, $minutes, $slot, $crowd]) {
            $place = Place::firstOrCreate(
                ['slug' => Str::slug($name)],
                [
                    'district_id' => $districtIds[Str::slug($district)]
                        ?? throw new RuntimeException("Unknown district [{$district}] for place [{$name}]."),
                    'name' => $name,
                    'wikipedia_title' => $wikipediaTitle,
                    'visit_minutes' => $minutes,
                    'best_time_slot' => $slot,
                    'crowd_level' => $crowd,
                    'is_hidden_gem' => in_array('gems', $categories, true),
                    'status' => PublishStatus::Draft,
                    'source' => 'seed',
                ],
            );

            $place->categories()->syncWithoutDetaching(array_map(
                fn (string $key) => $categoryIds[self::CATEGORY_KEYS[$key] ?? ''] ?? throw new RuntimeException("Unknown category [{$key}] for place [{$name}]."),
                $categories,
            ));
        }
    }
}
