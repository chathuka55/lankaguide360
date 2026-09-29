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
        ];
    }
}
