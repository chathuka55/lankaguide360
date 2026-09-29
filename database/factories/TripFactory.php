<?php

namespace Database\Factories;

use App\Enums\Tier;
use App\Enums\TripStatus;
use App\Models\Trip;
use App\Models\TripDay;
use App\Models\TripStop;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Trip>
 */
class TripFactory extends Factory
{
    public function definition(): array
    {
        return [
            'reference' => 'LG360-'.now()->year.'-'.fake()->unique()->numerify('#####'),
            'user_id' => User::factory(),
            'tier' => fake()->randomElement(Tier::cases()),
            'start_date' => now()->addMonth()->startOfDay(),
            'days' => 3,
            'adults' => 2,
            'status' => TripStatus::Draft,
            'estimated_total' => fake()->randomFloat(2, 500, 5000),
            'access_token' => Str::random(40),
        ];
    }

    public function guest(): static
    {
        return $this->state(fn () => ['user_id' => null]);
    }

    public function status(TripStatus $status): static
    {
        return $this->state(fn () => [
            'status' => $status,
            'submitted_at' => $status === TripStatus::Draft ? null : now(),
        ]);
    }

    /**
     * Add one TripDay per trip day, each with $stopsPerDay stops at new places.
     */
    public function withDays(int $stopsPerDay = 2): static
    {
        return $this->afterCreating(function (Trip $trip) use ($stopsPerDay) {
            foreach (range(1, $trip->days) as $number) {
                TripDay::factory()
                    ->for($trip)
                    ->has(TripStop::factory()->count($stopsPerDay)->sequence(fn ($s) => ['sequence' => $s->index + 1]), 'stops')
                    ->create([
                        'day_number' => $number,
                        'date' => $trip->start_date->copy()->addDays($number - 1),
                    ]);
            }
        });
    }
}
