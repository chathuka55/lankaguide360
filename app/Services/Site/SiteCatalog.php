<?php

namespace App\Services\Site;

use App\Models\Category;
use App\Models\Province;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

/**
 * Small lists used on almost every public page (footer, chips, filters), cached for an hour
 * (NFR-03). Category/district edits in the admin clear the cache.
 *
 * Only plain arrays are cached: Laravel refuses to unserialize objects from the cache
 * (cache.serializable_classes), so callers get lightweight objects rebuilt from arrays.
 */
class SiteCatalog
{
    public const CACHE_KEYS = ['site.categories', 'site.provinces'];

    /**
     * @return Collection<int, object{id: int, name: string, slug: string, icon: ?string}>
     */
    public function categories(): Collection
    {
        $rows = Cache::remember('site.categories', now()->addHour(), fn () => Category::orderBy('id')
            ->get(['id', 'name', 'slug', 'icon'])
            ->map(fn (Category $category) => $category->only(['id', 'name', 'slug', 'icon']))
            ->all());

        return collect($rows)->map(fn (array $row) => (object) $row);
    }

    /**
     * Provinces with their districts, alphabetical.
     *
     * @return Collection<int, object{id: int, name: string, slug: string, districts: Collection}>
     */
    public function provinces(): Collection
    {
        $rows = Cache::remember('site.provinces', now()->addHour(), fn () => Province::with(['districts' => fn ($q) => $q->orderBy('name')])
            ->orderBy('name')
            ->get()
            ->map(fn (Province $province) => [
                ...$province->only(['id', 'name', 'slug']),
                'districts' => $province->districts->map(fn ($district) => $district->only(['id', 'name', 'slug']))->all(),
            ])
            ->all());

        return collect($rows)->map(fn (array $row) => (object) [
            ...$row,
            'districts' => collect($row['districts'])->map(fn (array $district) => (object) $district),
        ]);
    }

    public static function flush(): void
    {
        foreach (self::CACHE_KEYS as $key) {
            Cache::forget($key);
        }
    }
}
