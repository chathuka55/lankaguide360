<?php

namespace App\Http\Requests\Admin;

use App\Enums\PublishStatus;
use App\Enums\Tier;
use App\Models\Hotel;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class HotelRequest extends FormRequest
{
    public const TYPES = ['hotel', 'guest_house', 'hostel', 'resort', 'apartment', 'villa', 'homestay', 'bungalow'];

    public function authorize(): bool
    {
        $hotel = $this->route('hotel');

        return $hotel ? $this->user()->can('update', $hotel) : $this->user()->can('create', Hotel::class);
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'slug' => Str::slug($this->input('slug') ?: $this->input('name', '').' '.$this->input('town', '')),
            'amenities' => array_values(array_filter((array) $this->input('amenities', []))),
        ]);
    }

    public function rules(): array
    {
        $publishing = $this->input('status') === PublishStatus::Published->value;

        return [
            'name' => ['required', 'string', 'max:150'],
            'slug' => ['required', 'string', 'max:170', 'alpha_dash', Rule::unique('hotels', 'slug')->ignore($this->route('hotel'))],
            'district_id' => ['required', 'integer', 'exists:districts,id'],
            'town' => ['required', 'string', 'max:80'],
            'type' => ['nullable', Rule::in(self::TYPES)],
            'tier' => ['required', Rule::enum(Tier::class)],
            'star_rating' => ['nullable', 'integer', 'between:1,5'],
            'kid_friendly' => ['boolean'],
            'lat' => [Rule::requiredIf($publishing), 'nullable', 'numeric', 'between:5.8,10'],
            'lng' => [Rule::requiredIf($publishing), 'nullable', 'numeric', 'between:79.4,82'],
            'amenities' => ['array', 'max:30'],
            'amenities.*' => ['string', 'max:40', 'regex:/^[a-z0-9_]+$/'],
            'website' => ['nullable', 'url', 'max:255'],
            'phone' => ['nullable', 'string', 'max:60'],
            'address' => ['nullable', 'string', 'max:255'],
            'wikipedia_title' => ['nullable', 'string', 'max:200'],
            'is_active' => ['boolean'],
            'status' => ['required', Rule::enum(PublishStatus::class)],
        ];
    }

    public function messages(): array
    {
        return [
            'lat.required' => 'Set the map position before publishing.',
            'lng.required' => 'Set the map position before publishing.',
            'amenities.*.regex' => 'Amenities use lowercase letters, numbers and underscores.',
        ];
    }
}
