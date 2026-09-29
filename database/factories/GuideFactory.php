<?php

namespace Database\Factories;

use App\Enums\GuideType;
use App\Models\Guide;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Guide>
 */
class GuideFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => 'Chauffeur-guide (English)',
            'type' => GuideType::Chauffeur,
            'languages' => ['English'],
            'day_rate' => 15,
        ];
    }
}
