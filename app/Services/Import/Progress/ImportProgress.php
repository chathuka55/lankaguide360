<?php

namespace App\Services\Import\Progress;

/**
 * Progress reporting for importers: a console progress bar, or nothing when queued.
 */
interface ImportProgress
{
    public function start(int $total, string $label = ''): void;

    public function advance(): void;

    public function finish(): void;
}
