<?php

namespace App\Enums;

enum UserRole: string
{
    case Traveller = 'traveller';
    case Agent = 'agent';
    case Admin = 'admin';

    public function label(): string
    {
        return match ($this) {
            self::Traveller => 'Traveller',
            self::Agent => 'Travel Agent',
            self::Admin => 'Admin',
        };
    }

    /**
     * Staff roles can open the back office (/admin).
     */
    public function isStaff(): bool
    {
        return $this === self::Agent || $this === self::Admin;
    }
}
