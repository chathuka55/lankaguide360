<?php

namespace App\Http\Requests\Admin;

use App\Models\Faq;
use Illuminate\Foundation\Http\FormRequest;

class FaqRequest extends FormRequest
{
    public function authorize(): bool
    {
        $faq = $this->route('faq');

        return $faq ? $this->user()->can('update', $faq) : $this->user()->can('create', Faq::class);
    }

    public function rules(): array
    {
        return [
            'question' => ['required', 'string', 'max:255'],
            'answer' => ['required', 'string', 'max:3000'],
            'keywords' => ['nullable', 'string', 'max:255'],
            'sort_order' => ['nullable', 'integer', 'between:0,1000'],
            'is_active' => ['boolean'],
        ];
    }
}
