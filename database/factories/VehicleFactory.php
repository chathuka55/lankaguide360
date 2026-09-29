<?php

namespace Database\Factories;

use App\Enums\Tier;
use App\Models\Vehicle;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Vehicle>
 */
class VehicleFactory extends Factory
{
    public function definition(): array
    {
        return [
            'type' => 'Standard van',
            'example_model' => 'Toyota HiAce',
            'tier' => Tier::Budget,
            'min_pax' => 1,
            'max_pax' => 6,
            'luggage_capacity' => 6,
            'day_rate' => 45,
            'km_rate' => 0.40,
        ];
    }
}
