<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\WithoutTimestamps;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['trip_id', 'full_name', 'country', 'age', 'email', 'phone', 'whatsapp', 'passport_no', 'is_lead'])]
#[WithoutTimestamps]
class TripTraveller extends Model
{
    protected function casts(): array
    {
        return [
            'age' => 'integer',
            'is_lead' => 'boolean',
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
