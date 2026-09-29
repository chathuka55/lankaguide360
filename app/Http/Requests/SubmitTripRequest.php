<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

/**
 * FR-18: registered users submit with one click; guests give their contact details.
 * Everyone confirms consent for storing their personal data (NFR-10).
 */
class SubmitTripRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $guest = $this->user() === null;

        return [
            'special_requests' => ['nullable', 'string', 'max:2000'],
            'consent' => ['accepted'],
            'full_name' => [$guest ? 'required' : 'nullable', 'string', 'max:120'],
            'email' => [$guest ? 'required' : 'nullable', 'email', 'max:190'],
            'country' => [$guest ? 'required' : 'nullable', 'string', 'max:80'],
            'age' => [$guest ? 'required' : 'nullable', 'integer', 'between:16,110'],
            'phone' => [$guest ? 'required' : 'nullable', 'string', 'max:30', 'regex:/^\+\d[\d\s\-()]{6,}$/'],
            'whatsapp' => ['nullable', 'string', 'max:30', 'regex:/^\+\d[\d\s\-()]{6,}$/'],
            'companions' => ['nullable', 'array', 'max:40'],
            'companions.*.name' => ['nullable', 'string', 'max:120'],
            'companions.*.age' => ['nullable', 'integer', 'between:0,110'],
            'create_account' => ['nullable', 'boolean'],
            'password' => ['nullable', 'required_if:create_account,1', 'confirmed', Password::defaults()],
        ];
    }

    public function messages(): array
    {
        return [
            'consent.accepted' => 'Please agree to us storing your details so an agent can contact you.',
            'phone.regex' => 'Include your country code, e.g. +44 7700 900123.',
            'whatsapp.regex' => 'Include your country code, e.g. +44 7700 900123.',
        ];
    }
}
