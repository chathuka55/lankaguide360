<?php

namespace App\Console\Commands;

use App\Enums\MediaStatus;
use App\Models\Vehicle;
use App\Services\Import\CommonsClient;
use App\Services\Media\MediaLibrary;
use Illuminate\Console\Command;
use Throwable;

/**
 * Give every vehicle type a freely licensed Commons photo (database/seeders/data/vehicle_photos.php).
 * Vehicles that already have a photo are left alone, so admin uploads are never replaced.
 */
class VehiclePhotosCommand extends Command
{
    protected $signature = 'lg:vehicle-photos';

    protected $description = 'Import one freely licensed Wikimedia Commons photo for each vehicle type that has none';

    public function handle(CommonsClient $commons, MediaLibrary $library): int
    {
        $titles = require database_path('seeders/data/vehicle_photos.php');
        $vehicles = Vehicle::whereIn('type', array_keys($titles))->whereDoesntHave('allMedia')->get();

        if ($vehicles->isEmpty()) {
            $this->info('Every vehicle already has a photo.');

            return self::SUCCESS;
        }

        $candidates = collect($commons->files($vehicles->map(fn (Vehicle $v) => $titles[$v->type])->unique()->values()->all()))
            ->keyBy(fn (array $c) => str_replace('_', ' ', $c['title']));
        $imported = 0;

        foreach ($vehicles as $vehicle) {
            $candidate = $candidates->get($titles[$vehicle->type]);

            if (! $candidate || $candidate['rejected_because']) {
                $this->warn("{$vehicle->type}: {$titles[$vehicle->type]} is ".($candidate ? "not usable ({$candidate['rejected_because']})" : 'not on Commons').'.');

                continue;
            }

            try {
                $library->storeCommons($vehicle, $candidate, MediaStatus::Published);
                $imported++;
                $this->line("{$vehicle->type}: {$candidate['license']}, {$candidate['author']}");
            } catch (Throwable $e) {
                $this->warn("{$vehicle->type}: {$e->getMessage()}");
            }
        }

        $this->info("Imported {$imported} vehicle photo(s).");

        return self::SUCCESS;
    }
}
