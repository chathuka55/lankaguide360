<?php

namespace App\Http\Requests\Admin;

use App\Services\Import\ImporterRegistry;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class RunImportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->isAdmin();
    }

    public function rules(): array
    {
        return [
            'importer' => [$this->routeIs('admin.imports.run') ? 'required' : 'exclude', Rule::in(array_keys(ImporterRegistry::IMPORTERS))],
            'fresh' => ['boolean'],
            'limit' => ['nullable', 'integer', 'between:1,1000'],
        ];
    }

    /**
     * Options passed to the importer job.
     *
     * @return array<string, mixed>
     */
    public function importOptions(): array
    {
        return array_filter([
            'fresh' => $this->boolean('fresh'),
            'limit' => $this->validated('importer') === 'images' ? $this->validated('limit') : null,
        ], fn ($value) => $value !== null);
    }
}
