<?php

namespace Database\Factories;

use App\Enums\PublishStatus;
use App\Models\District;
use App\Models\Place;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Place>
 */
class PlaceFactory extends Factory
{
    public function definition(): array
    {
        $name = Str::title(fake()->unique()->words(3, true));

        return [
            'district_id' => District::factory(),
            'name' => $name,
            'slug' => Str::slug($name),
            'short_description' => fake()->sentence(),
            'lat' => fake()->randomFloat(6, 6.0, 9.7),
            'lng' => fake()->randomFloat(6, 79.8, 81.8),
            'visit_minutes' => fake()->randomElement([30, 60, 90, 120, 180]),
            'fee_foreign_adult' => fake()->randomElement([0, 10, 25, 35]),
            'fee_foreign_child' => 0,
            'status' => PublishStatus::Draft,
        ];
    }

    public function published(): static
    {
        return $this->state(fn () => ['status' => PublishStatus::Published]);
    }

    public function hiddenGem(): static
    {
        return $this->state(fn () => ['is_hidden_gem' => true, 'crowd_level' => 'low']);
    }
}
