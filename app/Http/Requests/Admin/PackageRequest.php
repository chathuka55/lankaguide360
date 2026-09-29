<?php

namespace App\Http\Requests\Admin;

use App\Enums\Tier;
use App\Models\Package;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class PackageRequest extends FormRequest
{
    public function authorize(): bool
    {
        $package = $this->route('package');

        return $package ? $this->user()->can('update', $package) : $this->user()->can('create', Package::class);
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['slug' => Str::slug($this->input('slug') ?: $this->input('name', ''))]);
    }

    public function rules(): array
    {
        return [
            'template_trip_id' => ['required', 'integer', 'exists:trips,id'],
            'name' => ['required', 'string', 'max:150'],
            'slug' => ['required', 'string', 'max:170', 'alpha_dash', Rule::unique('packages', 'slug')->ignore($this->route('package'))],
            'tier' => ['nullable', Rule::enum(Tier::class)],
            'days' => ['nullable', 'integer', 'between:1,21'],
            'from_price' => ['nullable', 'numeric', 'min:0', 'max:100000', 'decimal:0,2'],
            'cover_image' => ['nullable', 'string', 'max:255'],
            'summary' => ['nullable', 'string', 'max:300'],
            'inclusions' => ['nullable', 'string', 'max:5000'],
            'exclusions' => ['nullable', 'string', 'max:5000'],
            'is_featured' => ['boolean'],
            'sort_order' => ['nullable', 'integer', 'between:-1000,1000'],
        ];
    }
}
