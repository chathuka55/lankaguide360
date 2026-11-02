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
            // Self-drive: Sri Lankan residents with a valid Sri Lankan licence only.
            'mode' => ['nullable', Rule::in(['driver', 'self'])],
            'driver_name' => ['exclude_unless:mode,self', 'required', 'string', 'max:120'],
            'licence_no' => ['exclude_unless:mode,self', 'required', 'string', 'max:20', 'regex:/^[A-Za-z]{0,2}\s?\d{5,10}$/'],
            'licence_expiry' => ['exclude_unless:mode,self', 'required', 'date', 'after_or_equal:end_date'],
            'resident' => ['exclude_unless:mode,self', 'accepted'],
            // Honeypot: hidden from people, filled in by bots.
            'website' => ['nullable', 'string', 'max:255'],
        ];
    }

    public function messages(): array
    {
        return [
            'licence_no.regex' => 'Enter the number as printed on your Sri Lankan driving licence, e.g. B1234567.',
            'licence_expiry.after_or_equal' => 'Your licence must be valid until the last day of the hire.',
            'resident.accepted' => 'Self-drive is only for Sri Lankan residents with a valid Sri Lankan driving licence.',
        ];
    }

    public function isSelfDrive(): bool
    {
        return $this->input('mode') === 'self';
    }

    public function isSpam(): bool
    {
        return filled($this->input('website'));
    }
}
