<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

#[Fillable(['importer', 'target_type', 'target_id', 'status', 'message'])]
class ImportLog extends Model
{
    const UPDATED_AT = null;

    /**
     * @return MorphTo<Model, $this>
     */
    public function target(): MorphTo
    {
        return $this->morphTo();
    }
}
