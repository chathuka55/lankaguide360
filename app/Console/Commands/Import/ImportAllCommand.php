<?php

namespace App\Console\Commands\Import;

use App\Jobs\RunImport;
use App\Services\Import\ImporterRegistry;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Bus;

class ImportAllCommand extends Command
{
    protected $signature = 'lg:import-all
        {--fresh : Ignore cached API responses}
        {--queue : Run the importers as a chained queue job instead of now}';

    protected $description = 'Run every importer in order: districts, places, images, hotels, place suggestions';

    /** Artisan command for each importer, in ImporterRegistry::ORDER. */
    private const COMMANDS = [
        'districts' => 'lg:import-districts',
        'places' => 'lg:import-places',
        'images' => 'lg:import-images',
        'hotels' => 'lg:import-hotels',
        'suggest-places' => 'lg:suggest-places',
    ];

    public function handle(): int
    {
        if ($this->option('queue')) {
            Bus::chain(array_map(
                fn (string $name) => new RunImport($name, ['fresh' => (bool) $this->option('fresh')]),
                ImporterRegistry::ORDER,
            ))->dispatch();

            $this->components->info('Queued all importers as one chain. Run `php artisan queue:work` to process them.');

            return self::SUCCESS;
        }

        $status = self::SUCCESS;

        foreach (ImporterRegistry::ORDER as $name) {
            $this->components->info('Running '.self::COMMANDS[$name]);
            $exit = $this->call(self::COMMANDS[$name], $this->option('fresh') ? ['--fresh' => true] : []);

            // Nothing later works without district shapes.
            if ($name === 'districts' && $exit !== self::SUCCESS) {
                $this->components->error('District import failed; stopping.');

                return self::FAILURE;
            }

            $status = $exit === self::SUCCESS ? $status : self::FAILURE;
        }

        return $status;
    }
}
