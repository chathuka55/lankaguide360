<?php

namespace App\Enums;

enum BestTimeSlot: string
{
    case Any = 'any';
    case Sunrise = 'sunrise';
    case Morning = 'morning';
    case Afternoon = 'afternoon';
    case Sunset = 'sunset';
}
