<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\WithoutTimestamps;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'trip_id', 'day_number', 'date', 'title', 'overnight_town', 'hotel_id', 'room_rate_id',
    'rooms', 'drive_km', 'drive_minutes',
])]
#[WithoutTimestamps]
class TripDay extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return [
            'day_number' => 'integer',
            'date' => 'date',
            'rooms' => 'integer',
            'drive_km' => 'decimal:1',
            'drive_minutes' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<Trip, $this>
     */
    public function trip(): BelongsTo
    {
        return $this->belongsTo(Trip::class);
    }

    /**
     * @return BelongsTo<Hotel, $this>
     */
    public function hotel(): BelongsTo
    {
        return $this->belongsTo(Hotel::class);
    }

    /**
     * @return BelongsTo<RoomRate, $this>
     */
    public function roomRate(): BelongsTo
    {
        return $this->belongsTo(RoomRate::class);
    }

    /**
     * @return HasMany<TripStop, $this>
     */
    public function stops(): HasMany
    {
        return $this->hasMany(TripStop::class)->orderBy('sequence');
    }
}
