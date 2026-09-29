<?php

namespace App\Models;

use App\Enums\SenderRole;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['trip_id', 'sender_id', 'sender_role', 'body', 'read_at'])]
class Message extends Model
{
    const UPDATED_AT = null;

    protected function casts(): array
    {
        return [
            'sender_role' => SenderRole::class,
            'read_at' => 'datetime',
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
     * @return BelongsTo<User, $this>
     */
    public function sender(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sender_id');
    }
}
