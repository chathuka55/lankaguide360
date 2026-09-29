<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['name', 'email', 'phone', 'subject', 'body'])]
class ContactMessage extends Model
{
    const UPDATED_AT = null;

    protected function casts(): array
    {
        return [
            'is_read' => 'boolean',
        ];
    }
}
