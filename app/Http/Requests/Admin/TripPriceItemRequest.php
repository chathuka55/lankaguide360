<?php

namespace App\Http\Requests\Admin;

use App\Enums\PriceCategory;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class TripPriceItemRequest extends FormRequest
{
    public function authorize(): bool
    {
        $trip = $this->route('trip') ?? $this->route('item')?->trip;

        return $trip !== null && $this->user()->can('update', $trip);
    }

    public function rules(): array
    {
        return [
            'category' => ['required', Rule::enum(PriceCategory::class)],
            'description' => ['required', 'string', 'max:200'],
            'qty' => ['required', 'numeric', 'min:0', 'max:10000'],
            'unit_price' => ['required', 'numeric', 'min:-100000', 'max:100000', 'decimal:0,2'],
        ];
    }
}
