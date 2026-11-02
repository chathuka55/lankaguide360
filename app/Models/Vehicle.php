<?php

namespace App\Models;

use App\Enums\Tier;
use App\Models\Concerns\HasMedia;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Attributes\WithoutTimestamps;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'type', 'example_model', 'description', 'tier', 'min_pax', 'max_pax', 'luggage_capacity', 'amenities', 'is_rentable',
    'provider_name', 'provider_phone', 'provider_email', 'provider_website', 'day_rate', 'km_rate', 'image',
    'self_drive', 'self_drive_day_rate', 'self_drive_deposit', 'self_drive_km_per_day',
])]
#[WithoutTimestamps]
class Vehicle extends Model
{
    use HasFactory, HasMedia;

    /** Services a vehicle can offer (feedback: show what is on board). */
    public const AMENITIES = [
        'air_conditioning' => 'Air conditioning',
        'wifi' => 'Wi-Fi',
        'charging' => 'Phone charging',
        'water' => 'Drinking water',
        'soft_drinks' => 'Soft drinks',
        'snacks' => 'Snacks',
        'child_seat' => 'Child seats',
        'cooler_box' => 'Cooler box',
        'reclining_seats' => 'Reclining seats',
    ];

    /** Services a new vehicle gets per tier (admins can change them). */
    public const DEFAULT_AMENITIES = [
        'budget' => ['air_conditioning', 'water', 'charging'],
        'premium' => ['air_conditioning', 'water', 'charging', 'wifi', 'soft_drinks'],
        'luxury' => ['air_conditioning', 'water', 'charging', 'wifi', 'soft_drinks', 'snacks', 'child_seat'],
    ];

    protected function casts(): array
    {
        return [
            'tier' => Tier::class,
            'min_pax' => 'integer',
            'max_pax' => 'integer',
            'luggage_capacity' => 'integer',
            'day_rate' => 'decimal:2',
            'km_rate' => 'decimal:2',
            'amenities' => 'array',
            'is_rentable' => 'boolean',
            'self_drive' => 'boolean',
            'self_drive_day_rate' => 'decimal:2',
            'self_drive_deposit' => 'decimal:2',
            'self_drive_km_per_day' => 'integer',
        ];
    }

    /**
     * @return HasMany<Trip, $this>
     */
    public function trips(): HasMany
    {
        return $this->hasMany(Trip::class);
    }

    /** Can be hired without a driver (Sri Lankan residents with a valid licence only). */
    public function offersSelfDrive(): bool
    {
        return $this->self_drive && (float) $this->self_drive_day_rate > 0;
    }

    #[Scope]
    protected function selfDrive(Builder $query): void
    {
        $query->where($this->qualifyColumn('self_drive'), true)->where($this->qualifyColumn('self_drive_day_rate'), '>', 0);
    }

    #[Scope]
    protected function tier(Builder $query, Tier|string $tier): void
    {
        $query->where($this->qualifyColumn('tier'), $tier instanceof Tier ? $tier->value : $tier);
    }

    /**
     * Vehicles whose passenger range includes $pax (SRS 5.5).
     */
    #[Scope]
    protected function forPax(Builder $query, int $pax): void
    {
        $query->where('min_pax', '<=', $pax)->where('max_pax', '>=', $pax);
    }
}
