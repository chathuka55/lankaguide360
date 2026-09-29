<?php

namespace App\Services\Import;

use App\Enums\PublishStatus;
use App\Models\District;
use App\Models\Place;
use App\Support\Geo;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * Suggests extra attractions, viewpoints, waterfalls, peaks, beaches and historic sites from
 * OpenStreetMap as draft places (source=osm) for admin approval. Places without a Wikipedia
 * or Wikidata link are low-profile, so they are flagged as hidden-gem candidates.
 */
class OverpassPlaceSuggester extends OverpassImporter
{
    /** Skip a suggestion if an existing place with a similar name is this close. */
    public const DUPLICATE_RADIUS_KM = 0.3;

    private const HISTORIC = 'archaeological_site|ruins|fort|castle|monument|monastery|temple|city_gate|citywalls|building|church|tower';

    public function key(): string
    {
        return 'osm-places';
    }

    protected function statements(): string
    {
        return 'nwr["tourism"~"^(attraction|viewpoint)$"]["name"]({{bbox}});'
            .'nwr["natural"~"^(waterfall|peak|beach)$"]["name"]({{bbox}});'
            .'nwr["waterway"="waterfall"]["name"]({{bbox}});'
            .'nwr["historic"~"^('.self::HISTORIC.')$"]["name"]({{bbox}});';
    }

    protected function importDistrict(District $district, Collection $elements): void
    {
        $existing = Place::where('district_id', $district->id)->get(['id', 'name', 'slug', 'lat', 'lng', 'osm_id']);
        $linkedCategories = $district->categories()->pluck('categories.id', 'slug');
        $max = (int) config('lankaguide.import.suggestions_per_district');
        $created = $skipped = 0;
        $seenNames = [];

        // Well-documented features first, then everything else.
        $candidates = $elements
            ->filter(fn ($e) => $this->name($e) !== null)
            ->sortByDesc(fn ($e) => (isset($e['tags']['wikipedia']) || isset($e['tags']['wikidata']) ? 2 : 0)
                + (isset($e['tags']['tourism']) || isset($e['tags']['natural']) ? 1 : 0));

        foreach ($candidates as $element) {
            if ($created >= $max) {
                break;
            }

            $name = $this->name($element);
            $normalized = $this->normalize($name);

            if (isset($seenNames[$normalized]) || $this->isDuplicate($element, $name, $existing)) {
                $skipped++;

                continue;
            }

            $seenNames[$normalized] = true;
            $kind = $this->kind($element['tags']);
            $wellKnown = isset($element['tags']['wikipedia']) || isset($element['tags']['wikidata']);

            $place = Place::create([
                'district_id' => $district->id,
                'name' => Str::limit($name, 150, ''),
                'slug' => $this->uniqueSlug($name, $district->slug),
                'lat' => round((float) $element['lat'], 6),
                'lng' => round((float) $element['lng'], 6),
                'visit_minutes' => $this->visitMinutes($kind),
                'best_time_slot' => match ($kind) {
                    'peak' => 'sunrise',
                    'viewpoint' => 'sunset',
                    default => 'any',
                },
                'crowd_level' => $wellKnown ? 'medium' : 'low',
                'is_hidden_gem' => ! $wellKnown,
                'status' => PublishStatus::Draft,
                'source' => 'osm',
                'osm_id' => $this->osmId($element),
                'wikipedia_title' => $this->wikipediaTitle($element['tags']),
            ]);

            // Only categories the district is linked to, so the builder can show the place.
            $categories = collect($this->categorySlugs($kind, ! $wellKnown))
                ->map(fn ($slug) => $linkedCategories[$slug] ?? null)
                ->filter();
            $place->categories()->sync($categories->all());

            $existing->push($place);
            $created++;
        }

        $this->report->created += $created;
        $this->report->skipped += $skipped;

        $this->log('success', $district, sprintf(
            '%s: %d named features found, %d suggested as draft places, %d skipped as duplicates.',
            $district->name, $candidates->count(), $created, $skipped,
        ));
    }

    private function isDuplicate(array $element, string $name, Collection $existing): bool
    {
        $osmId = $this->osmId($element);
        $normalized = $this->normalize($name);

        return $existing->contains(function (Place $place) use ($element, $normalized, $osmId) {
            if ($place->osm_id === $osmId || $this->normalize($place->name) === $normalized) {
                return true;
            }

            if ($place->lat === null || $place->lng === null) {
                return false;
            }

            $near = Geo::distanceKm((float) $element['lat'], (float) $element['lng'], (float) $place->lat, (float) $place->lng) <= self::DUPLICATE_RADIUS_KM;

            return $near && $this->similarNames($element['tags']['name:en'] ?? $element['tags']['name'], $place->name);
        });
    }

    /**
     * "Temple of the Tooth" ~ "Temple of the Sacred Tooth Relic": they share a significant
     * word, or the letters are at least 60% similar.
     */
    private function similarNames(string $a, string $b): bool
    {
        if (array_intersect($this->significantWords($a), $this->significantWords($b)) !== []) {
            return true;
        }

        [$na, $nb] = [$this->normalize($a), $this->normalize($b)];

        if ($na === '' || $nb === '') {
            return false;
        }

        similar_text($na, $nb, $percent);

        return $percent >= 60 || str_contains($na, $nb) || str_contains($nb, $na);
    }

    /**
     * @return array<int, string>
     */
    private function significantWords(string $name): array
    {
        $words = preg_split('/[^a-z0-9]+/', $this->stripGeneric(strtolower(Str::ascii($name)))) ?: [];

        return array_values(array_filter($words, fn ($word) => strlen($word) >= 4));
    }

    private function normalize(string $name): string
    {
        return preg_replace('/[^a-z0-9]/', '', $this->stripGeneric(strtolower(Str::ascii($name)))) ?? '';
    }

    /** Words that say what a place is, not which place it is. */
    private function stripGeneric(string $name): string
    {
        return preg_replace('/\b(the|of|sri lanka|temple|kovil|vihara|viharaya|raja maha|falls|fall|waterfall|ella|beach|rock|mountain|peak|national park|park|lake|view ?point|ruins|fort|church)\b/', ' ', $name) ?? '';
    }

    private function kind(array $tags): string
    {
        return match (true) {
            ($tags['natural'] ?? null) === 'beach' => 'beach',
            ($tags['natural'] ?? null) === 'waterfall', ($tags['waterway'] ?? null) === 'waterfall' => 'waterfall',
            ($tags['natural'] ?? null) === 'peak' => 'peak',
            ($tags['tourism'] ?? null) === 'viewpoint' => 'viewpoint',
            isset($tags['historic']) => 'historic',
            default => 'attraction',
        };
    }

    private function visitMinutes(string $kind): int
    {
        return match ($kind) {
            'peak' => 180,
            'beach' => 120,
            'waterfall' => 60,
            'viewpoint' => 45,
            default => 60,
        };
    }

    /**
     * @return array<int, string>
     */
    private function categorySlugs(string $kind, bool $hiddenGem): array
    {
        $slugs = match ($kind) {
            'beach' => ['beach-coastal'],
            'waterfall', 'viewpoint' => ['hill-country', 'nature-wildlife'],
            'peak' => ['hiking-adventure'],
            'historic' => ['historical-cultural'],
            default => [],
        };

        return $hiddenGem ? [...$slugs, 'hidden-gems'] : $slugs;
    }

    private function wikipediaTitle(array $tags): ?string
    {
        $value = $tags['wikipedia'] ?? '';

        return str_starts_with($value, 'en:') ? Str::limit(substr($value, 3), 200, '') : null;
    }

    private function uniqueSlug(string $name, string $districtSlug): string
    {
        $slug = Str::limit(Str::slug($name), 150, '');

        if (! Place::where('slug', $slug)->exists()) {
            return $slug;
        }

        $slug .= '-'.$districtSlug;
        $candidate = $slug;
        $n = 2;

        while (Place::where('slug', $candidate)->exists()) {
            $candidate = $slug.'-'.$n++;
        }

        return $candidate;
    }
}
