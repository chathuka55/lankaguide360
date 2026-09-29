<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Model;

/**
 * One cached drive leg between two coordinates (SRS 5.3 step 7).
 */
#[Table(name: 'route_cache')]
#[Fillable(['from_lat', 'from_lng', 'to_lat', 'to_lng', 'km', 'minutes', 'geometry', 'provider'])]
class RouteCache extends Model
{
    const CREATED_AT = null;

    protected function casts(): array
    {
        return [
            'from_lat' => 'decimal:6',
            'from_lng' => 'decimal:6',
            'to_lat' => 'decimal:6',
            'to_lng' => 'decimal:6',
            'km' => 'decimal:1',
            'minutes' => 'integer',
            'geometry' => 'array',
        ];
    }
}
