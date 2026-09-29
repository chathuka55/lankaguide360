<?php

namespace Database\Factories;

use App\Models\District;
use App\Models\Province;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<District>
 */
class DistrictFactory extends Factory
{
    public function definition(): array
    {
        $name = fake()->unique()->city();

        return [
            'province_id' => Province::factory(),
            'name' => $name,
            'slug' => Str::slug($name),
            // Inside Sri Lanka's bounding box.
            'lat' => fake()->randomFloat(6, 6.0, 9.7),
            'lng' => fake()->randomFloat(6, 79.8, 81.8),
        ];
    }
}
