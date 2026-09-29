<?php

namespace App\Http\Requests\Admin;

use App\Enums\TripStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class TripTransitionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('trip'));
    }

    public function rules(): array
    {
        $to = $this->input('status');

        return [
            'status' => ['required', Rule::enum(TripStatus::class)],
            'note' => [$to === TripStatus::Rejected->value ? 'required' : 'nullable', 'string', 'max:2000'],
            'final_total' => [$to === TripStatus::Approved->value ? 'required' : 'nullable', 'numeric', 'min:0', 'max:1000000', 'decimal:0,2'],
            'price_note' => ['nullable', 'string', 'max:500'],
        ];
    }

    public function messages(): array
    {
        return [
            'note.required' => 'Tell the traveller why the trip is rejected.',
            'final_total.required' => 'Set the final price before approving.',
        ];
    }
}
