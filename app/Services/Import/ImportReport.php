<?php

namespace App\Services\Import;

/**
 * Counters for one importer run, shown by the artisan commands and the admin import page.
 */
final class ImportReport
{
    public int $created = 0;

    public int $updated = 0;

    public int $skipped = 0;

    public int $failed = 0;

    /** @var array<int, string> */
    public array $notes = [];

    public function __construct(public readonly string $importer) {}

    public function note(string $message): void
    {
        $this->notes[] = $message;
    }

    public function summary(): string
    {
        return sprintf(
            '%s: %d created, %d updated, %d skipped, %d failed',
            $this->importer, $this->created, $this->updated, $this->skipped, $this->failed,
        );
    }
}
