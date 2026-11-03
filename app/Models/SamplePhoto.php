<?php

namespace App\Models;

use App\Models\Concerns\HasMedia;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\WithoutTimestamps;
use Illuminate\Database\Eloquent\Model;

/**
 * A freely licensed sample photo for hotels without their own photos (App\Services\Media\HotelSamplePhotos).
 */
#[Fillable(['name', 'subject', 'tiers', 'types', 'settings', 'commons_title'])]
#[WithoutTimestamps]
class SamplePhoto extends Model
{
    use HasMedia;

    protected function casts(): array
    {
        return [
            'tiers' => 'array',
            'types' => 'array',
            'settings' => 'array',
        ];
    }

    /** Whether this photo suits a hotel of this tier, type and setting (null lists match anything). */
    public function fits(?string $tier, ?string $type, string $setting): bool
    {
        return ($this->tiers === null || in_array($tier, $this->tiers, true))
            && ($this->types === null || in_array($type, $this->types, true))
            && ($this->settings === null || in_array($setting, $this->settings, true));
    }
}
