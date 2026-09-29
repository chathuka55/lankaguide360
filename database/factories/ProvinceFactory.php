<?php

namespace Database\Factories;

use App\Models\Province;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Province>
 */
class ProvinceFactory extends Factory
{
    public function definition(): array
    {
        $name = fake()->unique()->city().' Province';

        return [
            'name' => $name,
            'slug' => Str::slug($name),
        ];
    }
}
