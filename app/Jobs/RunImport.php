<?php

namespace App\Jobs;

use App\Services\Import\ImporterRegistry;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

/**
 * Runs one importer on the queue (`lg:import-* --queue`, and the admin import tools in phase 4).
 */
class RunImport implements ShouldQueue
{
    use Queueable;

    /** Importers pause between requests to respect rate limits, so allow a long run. */
    public int $timeout = 3600;

    public int $tries = 1;

    /**
     * @param  array<string, mixed>  $options
     */
    public function __construct(public string $importer, public array $options = []) {}

    public function handle(): void
    {
        $report = ImporterRegistry::make($this->importer)->run($this->options);

        Log::info('Import finished: '.$report->summary());
    }
}
