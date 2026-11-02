<?php

namespace App\Http\Requests\Admin;

use App\Enums\Tier;
use App\Models\Vehicle;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class VehicleRequest extends FormRequest
{
    public function authorize(): bool
    {
        $vehicle = $this->route('vehicle');

        return $vehicle ? $this->user()->can('update', $vehicle) : $this->user()->can('create', Vehicle::class);
    }

    public function rules(): array
    {
        return [
            'type' => ['required', 'string', 'max:60'],
            'example_model' => ['nullable', 'string', 'max:80'],
            'tier' => ['required', Rule::enum(Tier::class)],
            'min_pax' => ['required', 'integer', 'between:1,60'],
            'max_pax' => ['required', 'integer', 'between:1,60', 'gte:min_pax'],
            'luggage_capacity' => ['nullable', 'integer', 'between:0,100'],
            'day_rate' => ['required', 'numeric', 'min:0', 'max:10000', 'decimal:0,2'],
            'km_rate' => ['required', 'numeric', 'min:0', 'max:100', 'decimal:0,2'],
            'description' => ['nullable', 'string', 'max:2000'],
            'amenities' => ['nullable', 'array'],
            'amenities.*' => [Rule::in(array_keys(Vehicle::AMENITIES))],
            'is_rentable' => ['boolean'],
            'provider_name' => ['nullable', 'string', 'max:120'],
            'provider_phone' => ['nullable', 'string', 'max:30'],
            'provider_email' => ['nullable', 'email', 'max:190'],
            'provider_website' => ['nullable', 'url:https,http', 'max:255'],
            'self_drive' => ['boolean'],
            'self_drive_day_rate' => ['nullable', 'required_if_accepted:self_drive', 'numeric', 'min:0', 'max:10000', 'decimal:0,2'],
            'self_drive_deposit' => ['nullable', 'numeric', 'min:0', 'max:100000', 'decimal:0,2'],
            'self_drive_km_per_day' => ['nullable', 'integer', 'between:0,2000'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'is_rentable' => $this->boolean('is_rentable'),
            'self_drive' => $this->boolean('self_drive'),
            'amenities' => $this->input('amenities', []),
        ]);
    }
}
