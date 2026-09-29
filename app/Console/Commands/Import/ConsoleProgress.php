<?php

namespace App\Console\Commands\Import;

use App\Services\Import\Progress\ImportProgress;
use Illuminate\Console\OutputStyle;
use Symfony\Component\Console\Helper\ProgressBar;

final class ConsoleProgress implements ImportProgress
{
    private ?ProgressBar $bar = null;

    public function __construct(private OutputStyle $output) {}

    public function start(int $total, string $label = ''): void
    {
        if ($label !== '') {
            $this->output->writeln("<info>{$label}</info>");
        }

        $this->bar = $this->output->createProgressBar($total);
        $this->bar->start();
    }

    public function advance(): void
    {
        $this->bar?->advance();
    }

    public function finish(): void
    {
        $this->bar?->finish();
        $this->output->newLine(2);
    }
}
