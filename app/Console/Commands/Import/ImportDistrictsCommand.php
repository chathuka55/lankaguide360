<?php

namespace App\Console\Commands\Import;

class ImportDistrictsCommand extends ImportCommand
{
    protected $signature = 'lg:import-districts
        {--fresh : Ignore cached API responses}
        {--queue : Run on the queue instead of now}';

    protected $description = 'Download district boundaries from geoBoundaries and update district centroids';

    protected function importer(): string
    {
        return 'districts';
    }
}
