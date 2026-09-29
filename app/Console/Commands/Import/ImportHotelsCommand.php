<?php

namespace App\Console\Commands\Import;

class ImportHotelsCommand extends ImportCommand
{
    protected $signature = 'lg:import-hotels
        {--district= : Import a single district by slug}
        {--fresh : Ignore cached API responses}
        {--queue : Run on the queue instead of now}';

    protected $description = 'Import hotels, guest houses and hostels from OpenStreetMap (Overpass)';

    protected function importer(): string
    {
        return 'hotels';
    }

    protected function importerOptions(): array
    {
        return ['district' => $this->option('district')];
    }
}
