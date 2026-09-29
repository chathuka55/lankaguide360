<?php

namespace App\Http\Requests\Admin;

use App\Enums\BestTimeSlot;
use App\Enums\CrowdLevel;
use App\Enums\PublishStatus;
use App\Models\Place;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class PlaceRequest extends FormRequest
{
    public function authorize(): bool
    {
        $place = $this->route('place');

        return $place ? $this->user()->can('update', $place) : $this->user()->can('create', Place::class);
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['slug' => Str::slug($this->input('slug') ?: $this->input('name', ''))]);
    }

    public function rules(): array
    {
        $publishing = $this->input('status') === PublishStatus::Published->value;

        return [
            'name' => ['required', 'string', 'max:150'],
            'slug' => ['required', 'string', 'max:170', 'alpha_dash', Rule::unique('places', 'slug')->ignore($this->route('place'))],
            'district_id' => ['required', 'integer', 'exists:districts,id'],
            'categories' => [Rule::requiredIf($publishing), 'array'],
            'categories.*' => ['integer', 'exists:categories,id'],
            'short_description' => ['nullable', 'string', 'max:300'],
            'description' => ['nullable', 'string', 'max:20000'],
            'lat' => [Rule::requiredIf($publishing), 'nullable', 'numeric', 'between:5.8,10'],
            'lng' => [Rule::requiredIf($publishing), 'nullable', 'numeric', 'between:79.4,82'],
            'visit_minutes' => ['required', 'integer', 'between:10,720'],
            'open_time' => ['nullable', 'date_format:H:i'],
            'close_time' => ['nullable', 'date_format:H:i'],
            'best_time_slot' => ['required', Rule::enum(BestTimeSlot::class)],
            'fee_foreign_adult' => ['required', 'numeric', 'min:0', 'max:10000', 'decimal:0,2'],
            'fee_foreign_child' => ['required', 'numeric', 'min:0', 'max:10000', 'decimal:0,2'],
            'crowd_level' => ['required', Rule::enum(CrowdLevel::class)],
            'is_hidden_gem' => ['boolean'],
            'is_active' => ['boolean'],
            'best_months' => ['nullable', 'string', 'max:40'],
            'wikipedia_title' => ['nullable', 'string', 'max:200'],
            'wikipedia_url' => ['nullable', 'url', 'max:255'],
            'status' => ['required', Rule::enum(PublishStatus::class)],
        ];
    }

    public function messages(): array
    {
        return [
            'lat.required' => 'Set the map position before publishing.',
            'lng.required' => 'Set the map position before publishing.',
            'categories.required' => 'Choose at least one category before publishing.',
            'lat.between' => 'The latitude must be inside Sri Lanka (5.8 to 10).',
            'lng.between' => 'The longitude must be inside Sri Lanka (79.4 to 82).',
        ];
    }
}
