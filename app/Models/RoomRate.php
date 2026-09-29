<?php

namespace App\Models;

use App\Enums\MealPlan;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Attributes\WithoutTimestamps;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

#[Fillable([
    'hotel_id', 'room_type', 'meal_plan', 'price_per_night', 'max_occupancy',
    'extra_bed_price', 'season_from', 'season_to', 'is_estimate',
])]
#[WithoutTimestamps]
class RoomRate extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return [
            'meal_plan' => MealPlan::class,
            'price_per_night' => 'decimal:2',
            'max_occupancy' => 'integer',
            'extra_bed_price' => 'decimal:2',
            'season_from' => 'date',
            'season_to' => 'date',
            'is_estimate' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<Hotel, $this>
     */
    public function hotel(): BelongsTo
    {
        return $this->belongsTo(Hotel::class);
    }

    /**
     * Rates valid on a date: open-ended seasons (null dates) always match.
     */
    #[Scope]
    protected function validOn(Builder $query, Carbon|string $date): void
    {
        $query->where(fn (Builder $q) => $q->whereNull('season_from')->orWhereDate('season_from', '<=', $date))
            ->where(fn (Builder $q) => $q->whereNull('season_to')->orWhereDate('season_to', '>=', $date));
    }
}
