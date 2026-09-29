<?php

namespace App\Http\Requests\Admin;

use App\Enums\MealPlan;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class RoomRateRequest extends FormRequest
{
    public function authorize(): bool
    {
        $hotel = $this->route('hotel') ?? $this->route('rate')?->hotel;

        return $hotel !== null && $this->user()->can('update', $hotel);
    }

    public function rules(): array
    {
        return [
            'room_type' => ['required', 'string', 'max:60'],
            'meal_plan' => ['required', Rule::enum(MealPlan::class)],
            'price_per_night' => ['required', 'numeric', 'min:0', 'max:100000', 'decimal:0,2'],
            'max_occupancy' => ['required', 'integer', 'between:1,10'],
            'extra_bed_price' => ['nullable', 'numeric', 'min:0', 'max:10000', 'decimal:0,2'],
            'season_from' => ['nullable', 'date'],
            'season_to' => ['nullable', 'date', 'after_or_equal:season_from'],
            'is_estimate' => ['boolean'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function rate(): array
    {
        return [...$this->validated(), 'extra_bed_price' => $this->validated('extra_bed_price') ?? 0];
    }
}
