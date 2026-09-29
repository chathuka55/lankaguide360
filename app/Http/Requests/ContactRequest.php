<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ContactRequest extends FormRequest
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
            'phone' => ['nullable', 'string', 'max:30'],
            'subject' => ['nullable', 'string', 'max:150'],
            'body' => ['required', 'string', 'min:10', 'max:5000'],
            // Honeypot: hidden from people, filled in by bots.
            'website' => ['nullable', 'string', 'max:255'],
        ];
    }

    public function attributes(): array
    {
        return ['body' => 'message'];
    }

    public function isSpam(): bool
    {
        return filled($this->input('website'));
    }
}
