<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class TripMessageRequest extends FormRequest
{
    /** Access to the trip is checked in the controller (owner, staff or token). */
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return ['body' => ['required', 'string', 'max:3000']];
    }

    public function attributes(): array
    {
        return ['body' => 'message'];
    }
}
