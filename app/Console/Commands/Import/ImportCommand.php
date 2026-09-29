<?php

namespace App\Console\Commands\Import;

use App\Jobs\RunImport;
use App\Services\Import\ImporterRegistry;
use App\Services\Import\ImportException;
use Illuminate\Console\Command;

/**
 * Shared behaviour for the lg:import-* commands: run inline with a progress bar,
 * or dispatch to the queue with --queue.
 */
abstract class ImportCommand extends Command
{
    /** Key in ImporterRegistry::IMPORTERS. */
    abstract protected function importer(): string;

    /**
     * @return array<string, mixed>
     */
    protected function importerOptions(): array
    {
        return [];
    }

    public function handle(): int
    {
        $options = array_filter($this->importerOptions(), fn ($value) => $value !== null) + [
            'fresh' => (bool) $this->option('fresh'),
        ];

        if ($this->option('queue')) {
            RunImport::dispatch($this->importer(), $options);
            $this->components->info("Queued the [{$this->importer()}] import. Run `php artisan queue:work` to process it.");

            return self::SUCCESS;
        }

        try {
            $report = ImporterRegistry::make($this->importer())->run($options, new ConsoleProgress($this->output));
        } catch (ImportException $e) {
            $this->components->error($e->getMessage());

            return self::FAILURE;
        }

        foreach ($report->notes as $note) {
            $this->components->info($note);
        }

        $this->components->twoColumnDetail('<fg=green>Created</>', (string) $report->created);
        $this->components->twoColumnDetail('<fg=blue>Updated</>', (string) $report->updated);
        $this->components->twoColumnDetail('<fg=yellow>Skipped</>', (string) $report->skipped);
        $this->components->twoColumnDetail('<fg=red>Failed</>', (string) $report->failed);
        $this->newLine();
        $this->line('Details are in the import_logs table.');

        return $report->failed > 0 && $report->created + $report->updated === 0 ? self::FAILURE : self::SUCCESS;
    }
}
