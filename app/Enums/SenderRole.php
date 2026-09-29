<?php

namespace App\Enums;

enum SenderRole: string
{
    case Traveller = 'traveller';
    case Agent = 'agent';
    case System = 'system';
}
