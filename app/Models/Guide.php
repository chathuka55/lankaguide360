<?php

namespace App\Models;

use App\Enums\GuideType;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Attributes\WithoutTimestamps;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['name', 'type', 'languages', 'day_rate', 'phone', 'is_available'])]
#[WithoutTimestamps]
class Guide extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return [
            'type' => GuideType::class,
            'languages' => 'array',
            'day_rate' => 'decimal:2',
            'is_available' => 'boolean',
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
    protected function speaks(Builder $query, string $language): void
    {
        $query->whereJsonContains('languages', $language);
    }

    #[Scope]
    protected function available(Builder $query): void
    {
        $query->where('is_available', true);
    }
}
