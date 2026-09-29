<?php

namespace App\Enums;

enum PriceCategory: string
{
    case Accommodation = 'accommodation';
    case Transport = 'transport';
    case Guide = 'guide';
    case Meals = 'meals';
    case Tickets = 'tickets';
    case Activities = 'activities';
    case ServiceFee = 'service_fee';
    case Tax = 'tax';
    case Discount = 'discount';

    public function label(): string
    {
        return match ($this) {
            self::ServiceFee => 'Service fee',
            default => ucfirst($this->value),
        };
    }
}
