<?php

namespace Database\Factories;

use App\Models\Hotel;
use App\Models\RoomRate;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<RoomRate>
 */
class RoomRateFactory extends Factory
{
    public function definition(): array
    {
        return [
            'hotel_id' => Hotel::factory(),
            'room_type' => fake()->randomElement(['Standard double', 'Deluxe', 'Suite']),
            'meal_plan' => 'BB',
            'price_per_night' => fake()->randomElement([40, 90, 180, 350]),
            'max_occupancy' => 2,
            'extra_bed_price' => 20,
        ];
    }
}
