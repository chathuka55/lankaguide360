<?php

namespace App\Http\Requests\Admin;

use App\Models\Category;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class CategoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        $category = $this->route('category');

        return $category ? $this->user()->can('update', $category) : $this->user()->can('create', Category::class);
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['slug' => Str::slug($this->input('slug') ?: $this->input('name', ''))]);
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:60'],
            'slug' => ['required', 'string', 'max:80', 'alpha_dash', Rule::unique('categories', 'slug')->ignore($this->route('category'))],
            'icon' => ['nullable', 'string', 'max:60'],
            'districts' => ['array'],
            'districts.*' => ['integer', 'exists:districts,id'],
        ];
    }
}
