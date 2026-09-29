<?php

namespace App\Http\Requests\Admin;

use App\Models\Cuisine;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CuisineRequest extends FormRequest
{
    public function authorize(): bool
    {
        $cuisine = $this->route('cuisine');

        return $cuisine ? $this->user()->can('update', $cuisine) : $this->user()->can('create', Cuisine::class);
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:60', Rule::unique('cuisines', 'name')->ignore($this->route('cuisine'))],
        ];
    }
}
