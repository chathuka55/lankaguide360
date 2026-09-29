<?php

namespace App\Services\Import;

use App\Models\District;
use App\Support\Geo;
use Illuminate\Support\Facades\File;

/**
 * District shapes from public/geo/lk-districts.geojson (written by GeoBoundariesImporter).
 * Used by the Overpass importers to query each district and assign results to it.
 */
class DistrictGeometry
{
    /** Spelling variants seen in boundary data → our district slugs. */
    private const ALIASES = [
        'moneragala' => 'monaragala',
        'mulativu' => 'mullaitivu',
        'mullativu' => 'mullaitivu',
        'nuwaraeliya' => 'nuwara-eliya',
        'kegala' => 'kegalle',
        'hambanthota' => 'hambantota',
        'polonnaruva' => 'polonnaruwa',
        'trincomale' => 'trincomalee',
        'batticalo' => 'batticaloa',
        'puttlam' => 'puttalam',
    ];

    /** @var array<string, array>|null district slug => geometry */
    private ?array $geometries = null;

    /**
     * Resolve a boundary name such as "Moneragala District" to a district slug.
     *
     * @param  array<int, string>  $knownSlugs
     */
    public static function matchSlug(string $name, array $knownSlugs): ?string
    {
        $normalize = fn (string $value) => preg_replace('/[^a-z]/', '', strtolower($value));

        $key = $normalize(preg_replace('/\s+district$/i', '', trim($name)));
        $bySlug = collect($knownSlugs)->keyBy(fn (string $slug) => $normalize($slug));

        return $bySlug[$key] ?? (isset(self::ALIASES[$key]) && in_array(self::ALIASES[$key], $knownSlugs, true) ? self::ALIASES[$key] : null);
    }

    public function exists(): bool
    {
        return File::exists($this->path());
    }

    public function path(): string
    {
        return config('lankaguide.import.geojson_path');
    }

    public function for(District $district): ?array
    {
        return $this->all()[$district->slug] ?? null;
    }

    /**
     * @return array<string, array>
     */
    public function all(): array
    {
        if ($this->geometries !== null) {
            return $this->geometries;
        }

        if (! $this->exists()) {
            throw new ImportException('District shapes not found. Run `php artisan lg:import-districts` first.');
        }

        $data = json_decode(File::get($this->path()), true);

        return $this->geometries = collect($data['features'] ?? [])
            ->filter(fn ($feature) => isset($feature['properties']['slug'], $feature['geometry']))
            ->mapWithKeys(fn ($feature) => [$feature['properties']['slug'] => $feature['geometry']])
            ->all();
    }

    /**
     * @return array{south: float, west: float, north: float, east: float}|null
     */
    public function bbox(District $district): ?array
    {
        $geometry = $this->for($district);

        return $geometry ? Geo::bbox($geometry) : null;
    }

    public function contains(District $district, float $lat, float $lng): bool
    {
        $geometry = $this->for($district);

        return $geometry !== null && Geo::contains($geometry, $lat, $lng);
    }
}
