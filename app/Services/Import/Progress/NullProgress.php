<?php

namespace App\Services\Import\Progress;

final class NullProgress implements ImportProgress
{
    public function start(int $total, string $label = ''): void {}

    public function advance(): void {}

    public function finish(): void {}
}
