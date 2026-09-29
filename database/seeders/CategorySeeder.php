<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\District;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * 6 interest categories and their category → district links (SRS 4.1, data/categories.php).
 */
class CategorySeeder extends Seeder
{
    public function run(): void
    {
        $districtIds = District::pluck('id', 'slug');

        foreach (require __DIR__.'/data/categories.php' as $row) {
            $category = Category::firstOrCreate(
                ['slug' => $row['slug']],
                ['name' => $row['name'], 'icon' => $row['icon']],
            );

            $ids = collect([...$row['districts'], ...$row['extra_districts']])
                ->map(fn (string $name) => $districtIds[Str::slug($name)]
                    ?? throw new RuntimeException("Unknown district [{$name}] in category [{$row['name']}]."));

            $category->districts()->syncWithoutDetaching($ids->all());
        }
    }
}
