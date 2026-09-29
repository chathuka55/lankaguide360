<?php

namespace App\Http\Requests\Admin;

use App\Enums\GuideType;
use App\Enums\MealPlan;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Agent edits while reviewing a trip: hotels per night, vehicle, guide, meals, internal notes.
 */
class TripUpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('trip'));
    }

    public function rules(): array
    {
        return [
            'vehicle_id' => ['nullable', 'integer', 'exists:vehicles,id'],
            'guide_type' => ['required', Rule::enum(GuideType::class)],
            'guide_id' => ['nullable', 'integer', 'exists:guides,id'],
            'guide_language' => ['required', 'string', 'max:30'],
            'meal_plan' => ['required', Rule::enum(MealPlan::class)],
            'internal_notes' => ['nullable', 'string', 'max:5000'],
            'days' => ['array'],
            'days.*.hotel_id' => ['nullable', 'integer', 'exists:hotels,id'],
            'days.*.room_rate_id' => ['nullable', 'integer', 'exists:room_rates,id'],
            'days.*.overnight_town' => ['nullable', 'string', 'max:80'],
        ];
    }
}
