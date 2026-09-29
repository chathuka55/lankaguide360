<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\WithoutTimestamps;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

#[Fillable(['name', 'slug', 'icon'])]
#[WithoutTimestamps]
class Category extends Model
{
    use HasFactory;

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    /**
     * @return BelongsToMany<District, $this>
     */
    public function districts(): BelongsToMany
    {
        return $this->belongsToMany(District::class);
    }

    /**
     * @return BelongsToMany<Place, $this>
     */
    public function places(): BelongsToMany
    {
        return $this->belongsToMany(Place::class);
    }

    /**
     * @return BelongsToMany<Trip, $this>
     */
    public function trips(): BelongsToMany
    {
        return $this->belongsToMany(Trip::class, 'trip_category');
    }
}
