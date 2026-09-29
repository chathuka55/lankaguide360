<?php

namespace App\Services\Import;

use App\Models\Place;
use Illuminate\Support\Str;
use Throwable;

/**
 * Fills place descriptions, coordinates and the article link from the Wikipedia REST summary
 * endpoint (text CC BY-SA). A missing title is never guessed: the importer runs one search and
 * logs the suggestions for an admin to review.
 */
class WikipediaImporter extends Importer
{
    /** Rough bounding box of Sri Lanka, used to reject wrong coordinates. */
    private const LAT_RANGE = [5.8, 10.0];

    private const LNG_RANGE = [79.4, 82.0];

    public function key(): string
    {
        return 'wikipedia';
    }

    /**
     * Options: only (place slug), fresh.
     */
    protected function handle(array $options): void
    {
        $places = Place::query()
            ->whereNotNull('wikipedia_title')
            ->when($options['only'] ?? null, fn ($query, $slug) => $query->where('slug', $slug))
            ->orderBy('id')
            ->get();

        $this->progress->start($places->count(), 'Places');

        foreach ($places as $place) {
            try {
                $this->importPlace($place);
            } catch (Throwable $e) {
                $this->report->failed++;
                $this->log('failed', $place, $e->getMessage());
            }

            $this->progress->advance();
        }

        $this->progress->finish();
    }

    private function importPlace(Place $place): void
    {
        $title = str_replace(' ', '_', $place->wikipedia_title);
        $response = $this->client->get(
            rtrim(config('lankaguide.import.wikipedia_rest'), '/').'/page/summary/'.rawurlencode($title),
            [],
            ['group' => 'wikimedia', 'delay_ms' => config('lankaguide.import.delay_ms.wikimedia')],
        );

        if ($response->notFound()) {
            $this->report->skipped++;
            $this->log('not_found', $place, "No article titled \"{$place->wikipedia_title}\". ".$this->searchSuggestions($place));

            return;
        }

        $data = $response->json();

        if (! $response->ok() || $data === null) {
            throw new ImportException("Wikipedia returned HTTP {$response->status} for \"{$place->wikipedia_title}\".");
        }

        if (($data['type'] ?? '') === 'disambiguation') {
            $this->report->skipped++;
            $this->log('needs_review', $place, "\"{$place->wikipedia_title}\" is a disambiguation page. ".$this->searchSuggestions($place));

            return;
        }

        $extract = trim($data['extract'] ?? '');
        $values = [
            'short_description' => $extract !== '' ? $this->firstSentences($extract, 2) : null,
            'description' => $extract !== '' ? $extract : null,
            'wikipedia_url' => $data['content_urls']['desktop']['page'] ?? null,
        ];

        $notes = [];
        $lat = $data['coordinates']['lat'] ?? null;
        $lng = $data['coordinates']['lon'] ?? null;

        if ($lat !== null && $lng !== null) {
            if ($this->insideSriLanka((float) $lat, (float) $lng)) {
                $values['lat'] = round((float) $lat, 6);
                $values['lng'] = round((float) $lng, 6);
            } else {
                $notes[] = "coordinates {$lat},{$lng} are outside Sri Lanka and were ignored";
            }
        } else {
            $notes[] = 'article has no coordinates';
        }

        // Admin-reviewed (published) places only get empty fields filled, never overwritten.
        if ($place->isPublished()) {
            $values = array_filter($values, fn ($value, $field) => $value !== null && blank($place->{$field}), ARRAY_FILTER_USE_BOTH);
        } else {
            $values = array_filter($values, fn ($value) => $value !== null);
        }

        $place->fill($values);

        if (! $place->isDirty()) {
            $this->report->skipped++;
            $this->log('skipped', $place, 'Already up to date.'.($notes ? ' Note: '.implode('; ', $notes).'.' : ''));

            return;
        }

        $changed = array_keys($place->getDirty());
        $place->save();

        $this->report->updated++;
        $this->log('updated', $place, 'Updated '.implode(', ', $changed).' from Wikipedia.'.($notes ? ' Note: '.implode('; ', $notes).'.' : ''));
    }

    /**
     * One search, logged as suggestions for the admin (never applied automatically).
     */
    private function searchSuggestions(Place $place): string
    {
        try {
            $data = $this->client->get(config('lankaguide.import.wikipedia_api'), [
                'action' => 'query',
                'list' => 'search',
                'srsearch' => $place->name.' Sri Lanka',
                'srlimit' => 3,
                'format' => 'json',
                'formatversion' => 2,
            ], ['group' => 'wikimedia', 'delay_ms' => config('lankaguide.import.delay_ms.wikimedia')])->json();
        } catch (Throwable) {
            return 'Search failed; check the title manually.';
        }

        $titles = collect($data['query']['search'] ?? [])->pluck('title');

        return $titles->isEmpty()
            ? 'Search found no candidates.'
            : 'Search suggests: '.$titles->map(fn ($t) => "\"{$t}\"")->implode(', ').'. Set the correct wikipedia_title and re-run.';
    }

    private function firstSentences(string $text, int $count): string
    {
        $sentences = preg_split('/(?<=[.!?])\s+(?=[A-Z0-9"“(])/u', $text) ?: [$text];

        return Str::limit(implode(' ', array_slice($sentences, 0, $count)), 297);
    }

    private function insideSriLanka(float $lat, float $lng): bool
    {
        return $lat >= self::LAT_RANGE[0] && $lat <= self::LAT_RANGE[1]
            && $lng >= self::LNG_RANGE[0] && $lng <= self::LNG_RANGE[1];
    }
}
