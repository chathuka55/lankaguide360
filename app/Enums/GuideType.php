<?php

namespace App\Enums;

enum GuideType: string
{
    case Chauffeur = 'chauffeur';
    case National = 'national';
    case Site = 'site';
    case None = 'none';

    public function label(): string
    {
        return match ($this) {
            self::Chauffeur => 'Chauffeur-guide',
            self::National => 'SLTDA national guide',
            self::Site => 'Site guides only',
            self::None => 'No guide',
        };
    }
}
