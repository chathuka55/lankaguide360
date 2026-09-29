<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Query-string filters for /destinations. Invalid values are ignored rather than rejected.
 */
class DestinationFilterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'q' => ['nullable', 'string', 'max:100'],
            'category' => ['nullable', 'string', 'max:80'],
            'district' => ['nullable', 'string', 'max:80'],
            'province' => ['nullable', 'string', 'max:80'],
            'gems' => ['nullable', 'boolean'],
            'view' => ['nullable', 'in:grid,map'],
        ];
    }

    /**
     * @return array{q: ?string, category: ?string, district: ?string, province: ?string, gems: bool, view: string}
     */
    public function filters(): array
    {
        return [
            'q' => filled($this->query('q')) ? trim((string) $this->query('q')) : null,
            'category' => $this->query('category') ?: null,
            'district' => $this->query('district') ?: null,
            'province' => $this->query('province') ?: null,
            'gems' => $this->boolean('gems'),
            'view' => $this->query('view') === 'map' ? 'map' : 'grid',
        ];
    }

    protected function failedValidation(Validator $validator): void
    {
        // Bad filter values just fall back to defaults in filters().
    }
}
