<?php

namespace App\Http\Requests;

use App\Support\TripPoints;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class VehicleHireRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:190'],
            'phone' => ['required', 'string', 'max:30', 'regex:/^\+?\d[\d\s\-()]{6,}$/'],
            'start_date' => ['required', 'date', 'after_or_equal:today'],
            'end_date' => ['required', 'date', 'after_or_equal:start_date'],
            'pickup' => ['required', Rule::in(TripPoints::names())],
            'travellers' => ['required', 'integer', 'between:1,60'],
            'message' => ['nullable', 'string', 'max:2000'],
            // Honeypot: hidden from people, filled in by bots.
            'website' => ['nullable', 'string', 'max:255'],
        ];
    }

    public function isSpam(): bool
    {
        return filled($this->input('website'));
    }
}
