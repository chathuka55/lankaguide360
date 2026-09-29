<?php

namespace App\Services\Import;

use App\Enums\MediaStatus;
use App\Models\Place;
use App\Services\Media\MediaLibrary;
use Throwable;

/**
 * Imports freely licensed place photos from Wikimedia Commons: up to N images per place,
 * ≥ 1200 px wide, CC0 / CC BY / CC BY-SA / public domain only, converted to WebP variants,
 * saved as draft media rows with author, license and source URL.
 */
class CommonsImageImporter extends Importer
{
    public function __construct(ImportClient $client, private CommonsClient $commons, private MediaLibrary $library)
    {
        parent::__construct($client);
    }

    public function key(): string
    {
        return 'commons';
    }

    public static function acceptsLicense(?string $shortName): bool
    {
        return CommonsClient::acceptsLicense($shortName);
    }

    /**
     * Options: place (slug), limit (number of places), per_place (default 6), fresh.
     */
    protected function handle(array $options): void
    {
        $perPlace = (int) ($options['per_place'] ?? config('lankaguide.import.images_per_place'));

        $places = Place::query()
            ->withCount('media')
            ->when($options['place'] ?? null, fn ($query, $slug) => $query->where('slug', $slug))
            ->orderBy('id')
            ->get()
            ->filter(fn (Place $place) => $place->media_count < $perPlace)
            ->when($options['limit'] ?? null, fn ($places, $limit) => $places->take((int) $limit));

        $this->progress->start($places->count(), 'Places');

        foreach ($places as $place) {
            try {
                $this->importFor($place, $perPlace - $place->media_count);
            } catch (Throwable $e) {
                $this->report->failed++;
                $this->log('failed', $place, $e->getMessage());
            }

            $this->progress->advance();
        }

        $this->progress->finish();
    }

    private function importFor(Place $place, int $wanted): void
    {
        // Includes rejected photos, so an image an admin removed is never imported again.
        $known = $place->allMedia()->pluck('source_url')->filter()->all();
        $skipped = ['license' => 0, 'size' => 0, 'type' => 0, 'title' => 0, 'duplicate' => 0];
        $imported = 0;

        foreach ($this->commons->search($place->name.' Sri Lanka') as $candidate) {
            if ($imported >= $wanted) {
                break;
            }

            $reason = in_array($candidate['description_url'], $known, true) ? 'duplicate' : $candidate['rejected_because'];

            if ($reason !== null) {
                $skipped[$reason]++;

                continue;
            }

            try {
                $this->library->storeCommons($place, $candidate, MediaStatus::Draft);
                $imported++;
                $known[] = $candidate['description_url'];
            } catch (Throwable $e) {
                $this->report->failed++;
                $this->log('failed', $place, "Could not import {$candidate['title']}: {$e->getMessage()}");
            }
        }

        $skippedText = collect($skipped)->filter()->map(fn ($n, $why) => "{$n} {$why}")->implode(', ');

        if ($imported === 0) {
            $this->report->skipped++;
            $this->log('not_found', $place, 'No suitable Commons photos found'.($skippedText ? " (skipped: {$skippedText})" : '').'.');

            return;
        }

        $this->report->created += $imported;
        $this->log('success', $place, "Imported {$imported} photo(s) from Commons".($skippedText ? " (skipped: {$skippedText})" : '').'.');
    }
}
