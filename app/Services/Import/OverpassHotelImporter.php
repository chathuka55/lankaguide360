<?php

namespace App\Services\Import;

use App\Enums\PublishStatus;
use App\Enums\Tier;
use App\Models\District;
use App\Models\Hotel;
use App\Support\Geo;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * Hotels, guest houses, hostels, resorts and apartments from OpenStreetMap, upserted by osm_id
 * as drafts. OSM has no prices or photos: admins add rates and photos (BUILD-PROMPTS 3.2).
 */
class OverpassHotelImporter extends OverpassImporter
{
    public function key(): string
    {
        return 'osm-hotels';
    }

    /**
     * 5 stars → luxury, 3–4 → premium, anything else (guest house, hostel, no stars) → budget.
     */
    public static function guessTier(?int $stars): Tier
    {
        return match (true) {
            $stars !== null && $stars >= 5 => Tier::Luxury,
            $stars !== null && $stars >= 3 => Tier::Premium,
            default => Tier::Budget,
        };
    }

    protected function statements(): string
    {
        // Towns and cities too, to name the nearest town for hotels without an address.
        return 'nwr["tourism"~"^(hotel|guest_house|hostel|resort|apartment)$"]({{bbox}});'
            .'node["place"~"^(city|town)$"]({{bbox}});';
    }

    protected function importDistrict(District $district, Collection $elements): void
    {
        $towns = $elements->filter(fn ($e) => isset($e['tags']['place']) && $this->name($e));
        $stays = $elements->filter(fn ($e) => isset($e['tags']['tourism']));
        $counts = ['created' => 0, 'updated' => 0, 'skipped' => 0];

        foreach ($stays as $element) {
            $name = $this->name($element);

            if ($name === null) {
                $counts['skipped']++;

                continue;
            }

            $tags = $element['tags'];
            $osmId = $this->osmId($element);
            $stars = isset($tags['stars']) && preg_match('/^\d/', $tags['stars']) ? min(5, (int) $tags['stars']) : null;

            $values = [
                'district_id' => $district->id,
                'name' => Str::limit($name, 150, ''),
                'type' => $tags['tourism'],
                'star_rating' => $stars ?: null,
                'lat' => round((float) $element['lat'], 6),
                'lng' => round((float) $element['lng'], 6),
                'town' => Str::limit($this->town($element, $towns, $district), 80, ''),
                'website' => Str::limit($tags['website'] ?? $tags['contact:website'] ?? $tags['url'] ?? '', 250, '') ?: null,
                'phone' => Str::limit($tags['phone'] ?? $tags['contact:phone'] ?? $tags['contact:mobile'] ?? '', 60, '') ?: null,
                'address' => Str::limit($this->address($tags), 250, '') ?: null,
                'amenities' => $this->amenities($tags),
            ];

            $hotel = Hotel::where('osm_id', $osmId)->first();

            if ($hotel === null) {
                Hotel::create($values + [
                    'osm_id' => $osmId,
                    'slug' => $this->uniqueSlug($name, $values['town'], $element['id']),
                    'tier' => self::guessTier($stars),
                    'status' => PublishStatus::Draft,
                ]);
                $counts['created']++;

                continue;
            }

            // Published hotels were reviewed by an admin: leave them alone.
            // Drafts get fresh OSM data, but keep tier/kid_friendly/slug that an admin may have set.
            if ($hotel->isPublished()) {
                $counts['skipped']++;

                continue;
            }

            $hotel->fill($values);

            if ($hotel->isDirty()) {
                $hotel->save();
                $counts['updated']++;
            } else {
                $counts['skipped']++;
            }
        }

        $this->report->created += $counts['created'];
        $this->report->updated += $counts['updated'];
        $this->report->skipped += $counts['skipped'];

        $this->log('success', $district, sprintf(
            '%s: %d stays found, %d created, %d updated, %d skipped (unnamed, unchanged or published).',
            $district->name, $stays->count(), $counts['created'], $counts['updated'], $counts['skipped'],
        ));
    }

    /**
     * addr:city, else the nearest OSM town/city in the district, else the district name.
     */
    private function town(array $element, Collection $towns, District $district): string
    {
        $tags = $element['tags'];

        foreach (['addr:city', 'addr:town', 'addr:village', 'addr:place'] as $key) {
            if (! empty($tags[$key])) {
                return trim($tags[$key]);
            }
        }

        $nearest = $towns
            ->map(fn ($town) => [$this->name($town), Geo::distanceKm($element['lat'], $element['lng'], $town['lat'], $town['lng'])])
            ->sortBy(1)
            ->first();

        return $nearest && $nearest[1] <= 25 ? $nearest[0] : $district->name;
    }

    private function address(array $tags): string
    {
        return collect([
            trim(($tags['addr:housenumber'] ?? '').' '.($tags['addr:street'] ?? '')),
            $tags['addr:city'] ?? $tags['addr:place'] ?? null,
            $tags['addr:postcode'] ?? null,
        ])->filter()->implode(', ');
    }

    /**
     * @return array<int, string>
     */
    private function amenities(array $tags): array
    {
        return array_keys(array_filter([
            'wifi' => in_array($tags['internet_access'] ?? '', ['wlan', 'yes', 'wifi'], true),
            'pool' => ($tags['swimming_pool'] ?? '') === 'yes',
            'air_conditioning' => ($tags['air_conditioning'] ?? '') === 'yes',
            'wheelchair' => ($tags['wheelchair'] ?? '') === 'yes',
        ]));
    }

    private function uniqueSlug(string $name, string $town, int $osmNumber): string
    {
        $slug = Str::limit(Str::slug($name.' '.$town), 150, '');

        return Hotel::where('slug', $slug)->exists() ? $slug.'-'.$osmNumber : $slug;
    }
}
