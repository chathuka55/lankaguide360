<?php

namespace Database\Factories;

use App\Models\Trip;
use App\Models\TripDay;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TripDay>
 */
class TripDayFactory extends Factory
{
    public function definition(): array
    {
        return [
            'trip_id' => Trip::factory(),
            'day_number' => 1,
            'date' => now()->addMonth()->startOfDay(),
            'title' => fake()->city().' → '.fake()->city(),
            'overnight_town' => fake()->city(),
            'rooms' => 1,
            'drive_km' => fake()->randomFloat(1, 20, 250),
            'drive_minutes' => fake()->numberBetween(30, 360),
        ];
    }
}
