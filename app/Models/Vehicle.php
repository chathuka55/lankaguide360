<?php

namespace App\Models;

use App\Enums\Tier;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Attributes\WithoutTimestamps;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['type', 'example_model', 'tier', 'min_pax', 'max_pax', 'luggage_capacity', 'day_rate', 'km_rate', 'image'])]
#[WithoutTimestamps]
class Vehicle extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return [
            'tier' => Tier::class,
            'min_pax' => 'integer',
            'max_pax' => 'integer',
            'luggage_capacity' => 'integer',
            'day_rate' => 'decimal:2',
            'km_rate' => 'decimal:2',
        ];
    }

    /**
     * @return HasMany<Trip, $this>
     */
    public function trips(): HasMany
    {
        return $this->hasMany(Trip::class);
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
