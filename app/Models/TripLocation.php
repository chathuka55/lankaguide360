<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\WithoutTimestamps;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['trip_id', 'lat', 'lng', 'accuracy_m', 'source', 'recorded_at'])]
#[WithoutTimestamps]
class TripLocation extends Model
{
    protected function casts(): array
    {
        return [
            'lat' => 'decimal:6',
            'lng' => 'decimal:6',
            'accuracy_m' => 'integer',
            'recorded_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Trip, $this>
     */
    public function trip(): BelongsTo
    {
        return $this->belongsTo(Trip::class);
    }
}
