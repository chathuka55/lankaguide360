<?php

namespace Database\Factories;

use App\Enums\PublishStatus;
use App\Enums\Tier;
use App\Models\District;
use App\Models\Hotel;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Hotel>
 */
class HotelFactory extends Factory
{
    public function definition(): array
    {
        $name = fake()->unique()->lastName().' '.fake()->randomElement(['Hotel', 'Resort', 'Villa', 'Guest House']);

        return [
            'district_id' => District::factory(),
            'town' => fake()->city(),
            'name' => $name,
            'slug' => Str::slug($name),
            'type' => 'hotel',
            'tier' => fake()->randomElement(Tier::cases()),
            'star_rating' => fake()->numberBetween(2, 5),
            'lat' => fake()->randomFloat(6, 6.0, 9.7),
            'lng' => fake()->randomFloat(6, 79.8, 81.8),
            'amenities' => ['wifi', 'pool'],
            'status' => PublishStatus::Draft,
        ];
    }

    public function published(): static
    {
        return $this->state(fn () => ['status' => PublishStatus::Published]);
    }

    public function tier(Tier $tier): static
    {
        return $this->state(fn () => ['tier' => $tier]);
    }
}
