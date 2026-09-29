<?php

namespace Database\Factories;

use App\Models\Place;
use App\Models\TripDay;
use App\Models\TripStop;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TripStop>
 */
class TripStopFactory extends Factory
{
    public function definition(): array
    {
        return [
            'trip_day_id' => TripDay::factory(),
            'place_id' => Place::factory(),
            'sequence' => 1,
            'arrive_at' => '09:00:00',
            'depart_at' => '10:30:00',
            'km_from_prev' => fake()->randomFloat(1, 1, 120),
            'minutes_from_prev' => fake()->numberBetween(5, 180),
        ];
    }
}
