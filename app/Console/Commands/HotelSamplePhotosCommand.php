<?php

namespace App\Console\Commands;

use App\Enums\MediaStatus;
use App\Models\SamplePhoto;
use App\Services\Import\CommonsClient;
use App\Services\Media\MediaLibrary;
use Illuminate\Console\Command;
use Throwable;

/**
 * Load the hotel sample-photo pool (database/seeders/data/hotel_sample_photos.php) and import
 * each freely licensed Commons photo that isn't stored yet. Safe to run again.
 */
class HotelSamplePhotosCommand extends Command
{
    protected $signature = 'lg:hotel-sample-photos';

    protected $description = 'Import the freely licensed sample photos shown on hotels without photos of their own';

    public function handle(CommonsClient $commons, MediaLibrary $library): int
    {
        $rows = require database_path('seeders/data/hotel_sample_photos.php');
        foreach ($rows as [$title, $name, $subject, $tiers, $types, $settings]) {
            SamplePhoto::updateOrCreate(['commons_title' => $title], compact('name', 'subject', 'tiers', 'types', 'settings'));
        }

        // Photos taken out of the list are removed with their files.
        foreach (SamplePhoto::whereNotIn('commons_title', array_column($rows, 0))->get() as $old) {
            $library->deleteAllFor($old);
            $old->delete();
            $this->line("Removed {$old->name}.");
        }

        $missing = SamplePhoto::whereDoesntHave('allMedia')->get();
        if ($missing->isEmpty()) {
            $this->info('Every sample photo is already stored.');

            return self::SUCCESS;
        }

        $candidates = collect($commons->files($missing->pluck('commons_title')->all()))
            ->keyBy(fn (array $c) => str_replace('_', ' ', $c['title']));
        $imported = 0;

        foreach ($missing as $photo) {
            $candidate = $candidates->get($photo->commons_title);

            if (! $candidate || $candidate['rejected_because']) {
                $this->warn("{$photo->name}: {$photo->commons_title} is ".($candidate ? "not usable ({$candidate['rejected_because']})" : 'not on Commons').'.');

                continue;
            }

            try {
                // Our own neutral name as the alt text: the Commons description names other places.
                $library->storeCommons($photo, $candidate, MediaStatus::Published)->update(['alt' => $photo->name, 'caption' => null]);
                $imported++;
                $this->line("{$photo->name}: {$candidate['license']}, {$candidate['author']}");
            } catch (Throwable $e) {
                $this->warn("{$photo->name}: {$e->getMessage()}");
            }
        }

        $this->info("Imported {$imported} sample photo(s).");

        return self::SUCCESS;
    }
}
