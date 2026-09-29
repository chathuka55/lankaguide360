<?php

namespace Database\Seeders;

use App\Enums\Tier;
use App\Models\Package;
use App\Models\Place;
use App\Services\PackageService;
use Illuminate\Database\Seeder;

/**
 * Six starter packages (data/packages.php), each with a draft template trip. Idempotent: an
 * existing package (matched by slug) is left as the admin edited it.
 */
class PackageSeeder extends Seeder
{
    public function run(PackageService $packages): void
    {
        foreach (require __DIR__.'/data/packages.php' as $position => $data) {
            if (Package::where('slug', $data['slug'])->exists()) {
                continue;
            }

            $placeIds = Place::whereIn('name', collect($data['days'])->flatMap(fn ($day) => $day[1])->unique()->all())->pluck('id', 'name');

            $template = $packages->createTemplate(
                Tier::from($data['tier']),
                array_map(fn ($day) => [
                    'title' => $day[0],
                    'place_ids' => collect($day[1])->map(fn ($name) => $placeIds[$name] ?? null)->filter()->values()->all(),
                    'overnight_town' => $day[2],
                ], $data['days']),
                substr(md5($data['slug']), 0, 10),
            );

            Package::create([
                'template_trip_id' => $template->id,
                'name' => $data['name'],
                'slug' => $data['slug'],
                'tier' => $data['tier'],
                'days' => count($data['days']),
                'from_price' => $data['from_price'],
                'summary' => $data['summary'],
                'inclusions' => $data['inclusions'],
                'exclusions' => $data['exclusions'],
                'is_featured' => $data['featured'],
                'sort_order' => $position + 1,
            ]);
        }
    }
}
