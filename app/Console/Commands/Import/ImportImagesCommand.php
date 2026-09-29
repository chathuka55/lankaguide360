<?php

namespace App\Console\Commands\Import;

class ImportImagesCommand extends ImportCommand
{
    protected $signature = 'lg:import-images
        {--place= : Import photos for a single place by slug}
        {--limit= : Maximum number of places to process}
        {--per-place= : Photos wanted per place (default 6)}
        {--fresh : Ignore cached API responses}
        {--queue : Run on the queue instead of now}';

    protected $description = 'Import freely licensed place photos from Wikimedia Commons as WebP';

    protected function importer(): string
    {
        return 'images';
    }

    protected function importerOptions(): array
    {
        return [
            'place' => $this->option('place'),
            'limit' => $this->option('limit') !== null ? (int) $this->option('limit') : null,
            'per_place' => $this->option('per-place') !== null ? (int) $this->option('per-place') : null,
        ];
    }
}
