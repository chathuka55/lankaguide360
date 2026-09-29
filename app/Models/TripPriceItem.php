<?php

namespace App\Models;

use App\Enums\PriceCategory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\WithoutTimestamps;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['trip_id', 'category', 'description', 'qty', 'unit_price', 'amount'])]
#[WithoutTimestamps]
class TripPriceItem extends Model
{
    protected function casts(): array
    {
        return [
            'category' => PriceCategory::class,
            'qty' => 'decimal:2',
            'unit_price' => 'decimal:2',
            'amount' => 'decimal:2',
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
