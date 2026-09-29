<?php

namespace App\Console\Commands\Import;

class ImportPlacesCommand extends ImportCommand
{
    protected $signature = 'lg:import-places
        {--only= : Import a single place by slug}
        {--fresh : Ignore cached API responses}
        {--queue : Run on the queue instead of now}';

    protected $description = 'Fill place descriptions, coordinates and article links from Wikipedia';

    protected function importer(): string
    {
        return 'places';
    }

    protected function importerOptions(): array
    {
        return ['only' => $this->option('only')];
    }
}
