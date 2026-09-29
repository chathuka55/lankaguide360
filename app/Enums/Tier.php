<?php

namespace App\Enums;

enum Tier: string
{
    case Budget = 'budget';
    case Premium = 'premium';
    case Luxury = 'luxury';

    public function label(): string
    {
        return ucfirst($this->value);
    }
}
