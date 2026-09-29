<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

/**
 * Admin-editable key/value settings: service fee %, tax %, exchange rates (SRS 7.2).
 * Read with Setting::get('tax_percent'); values are cached until the next save.
 */
#[Table(key: 'key', keyType: 'string', incrementing: false, timestamps: false)]
#[Fillable(['key', 'value'])]
class Setting extends Model
{
    private const CACHE_KEY = 'settings.all';

    protected static function booted(): void
    {
        static::saved(fn () => static::flushCache());
        static::deleted(fn () => static::flushCache());
    }

    public static function flushCache(): void
    {
        Cache::forget(self::CACHE_KEY);
    }

    public static function get(string $key, ?string $default = null): ?string
    {
        return static::cached()[$key] ?? $default;
    }

    public static function put(string $key, ?string $value): void
    {
        static::updateOrCreate(['key' => $key], ['value' => $value]);
    }

    /**
     * @return array<string, string|null>
     */
    private static function cached(): array
    {
        return Cache::rememberForever(self::CACHE_KEY, fn () => static::query()->pluck('value', 'key')->all());
    }
}
