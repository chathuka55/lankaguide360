<?php

namespace Database\Factories;

use App\Enums\MediaSource;
use App\Models\Media;
use App\Models\Place;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Media>
 */
class MediaFactory extends Factory
{
    public function definition(): array
    {
        $base = 'media/place/1/'.fake()->unique()->slug(2);

        return [
            'mediable_type' => 'place',
            'mediable_id' => Place::factory(),
            'path' => $base.'-1600.webp',
            'variants' => [400 => $base.'-400.webp', 800 => $base.'-800.webp', 1600 => $base.'-1600.webp'],
            'width' => 1600,
            'height' => 1067,
            'alt' => fake()->sentence(4),
            'source' => MediaSource::Commons,
            'source_url' => 'https://commons.wikimedia.org/wiki/File:Example.jpg',
            'author' => fake()->name(),
            'license' => 'CC BY-SA 4.0',
            'license_url' => 'https://creativecommons.org/licenses/by-sa/4.0/',
        ];
    }

    public function cover(): static
    {
        return $this->state(fn () => ['is_cover' => true]);
    }
}
