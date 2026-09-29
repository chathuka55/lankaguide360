<?php

namespace App\Enums;

enum MealPlan: string
{
    case RoomOnly = 'RO';
    case BedAndBreakfast = 'BB';
    case HalfBoard = 'HB';
    case FullBoard = 'FB';
    case AllInclusive = 'AI';

    public function label(): string
    {
        return match ($this) {
            self::RoomOnly => 'Room only',
            self::BedAndBreakfast => 'Bed & Breakfast',
            self::HalfBoard => 'Half Board',
            self::FullBoard => 'Full Board',
            self::AllInclusive => 'All Inclusive',
        };
    }
}
