<?php

namespace App\Http\Requests\Admin;

use App\Models\Guide;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class GuideRequest extends FormRequest
{
    public function authorize(): bool
    {
        $guide = $this->route('guide');

        return $guide ? $this->user()->can('update', $guide) : $this->user()->can('create', Guide::class);
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'languages' => collect((array) $this->input('languages', []))
                ->map(fn ($language) => str(trim((string) $language))->replace('_', ' ')->title()->toString())
                ->filter()->unique()->values()->all(),
        ]);
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:120'],
            'type' => ['required', Rule::in(['chauffeur', 'national', 'site'])],
            'languages' => ['required', 'array', 'min:1', 'max:10'],
            'languages.*' => ['string', 'max:30'],
            'day_rate' => ['required', 'numeric', 'min:0', 'max:10000', 'decimal:0,2'],
            'phone' => ['nullable', 'string', 'max:30'],
            'is_available' => ['boolean'],
        ];
    }
}
