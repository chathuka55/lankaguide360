<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class DistrictRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('district'));
    }

    public function rules(): array
    {
        return [
            'description' => ['nullable', 'string', 'max:5000'],
            'image' => ['nullable', 'string', 'max:255'],
            'lat' => ['required', 'numeric', 'between:5.8,10'],
            'lng' => ['required', 'numeric', 'between:79.4,82'],
            'categories' => ['array'],
            'categories.*' => ['integer', 'exists:categories,id'],
        ];
    }
}
