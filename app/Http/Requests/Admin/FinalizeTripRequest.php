<?php

namespace App\Http\Requests\Admin;

use App\Models\TripReceipt;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class FinalizeTripRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('trip'));
    }

    public function rules(): array
    {
        return [
            'times' => ['array'],
            'times.*.arrive_at' => ['nullable', 'date_format:H:i'],
            'times.*.depart_at' => ['nullable', 'date_format:H:i'],
            'paid_usd' => ['nullable', 'numeric', 'min:0', 'max:1000000', 'decimal:0,2'],
            'payment_method' => ['nullable', Rule::in(array_keys(TripReceipt::PAYMENT_METHODS))],
            'payment_note' => ['nullable', 'string', 'max:255'],
        ];
    }

    public function after(): array
    {
        return [function (Validator $validator) {
            foreach ((array) $this->input('times', []) as $id => $time) {
                $arrive = $time['arrive_at'] ?? null;
                $depart = $time['depart_at'] ?? null;
                if ($arrive && $depart && $depart < $arrive) {
                    $validator->errors()->add("times.{$id}.depart_at", 'Leave time must be after the arrival time.');
                }
            }
        }];
    }

    public function messages(): array
    {
        return [
            'times.*.arrive_at.date_format' => 'Use a time like 08:30.',
            'times.*.depart_at.date_format' => 'Use a time like 10:00.',
        ];
    }
}
