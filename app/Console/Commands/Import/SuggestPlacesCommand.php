<?php

namespace App\Console\Commands\Import;

class SuggestPlacesCommand extends ImportCommand
{
    protected $signature = 'lg:suggest-places
        {--district= : Suggest places for a single district by slug}
        {--fresh : Ignore cached API responses}
        {--queue : Run on the queue instead of now}';

    protected $description = 'Suggest extra attractions, waterfalls, peaks, beaches and historic sites from OpenStreetMap as draft places';

    protected function importer(): string
    {
        return 'suggest-places';
    }

    protected function importerOptions(): array
    {
        return ['district' => $this->option('district')];
    }
}
