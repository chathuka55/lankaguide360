<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\WithoutTimestamps;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['trip_day_id', 'place_id', 'sequence', 'arrive_at', 'depart_at', 'km_from_prev', 'minutes_from_prev'])]
#[WithoutTimestamps]
class TripStop extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return [
            'sequence' => 'integer',
            'km_from_prev' => 'decimal:1',
            'minutes_from_prev' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<TripDay, $this>
     */
    public function tripDay(): BelongsTo
    {
        return $this->belongsTo(TripDay::class);
    }

    /**
     * @return BelongsTo<Place, $this>
     */
    public function place(): BelongsTo
    {
        return $this->belongsTo(Place::class);
    }
}
