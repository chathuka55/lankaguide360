<?php

namespace App\Http\Requests\Admin;

use App\Models\Review;
use Illuminate\Foundation\Http\FormRequest;

class ReviewRequest extends FormRequest
{
    public function authorize(): bool
    {
        $review = $this->route('review');

        return $review ? $this->user()->can('update', $review) : $this->user()->can('create', Review::class);
    }

    public function rules(): array
    {
        return [
            'author_name' => ['required', 'string', 'max:120'],
            'country' => ['nullable', 'string', 'max:80'],
            'rating' => ['required', 'integer', 'between:1,5'],
            'comment' => ['nullable', 'string', 'max:2000'],
            'is_approved' => ['boolean'],
        ];
    }
}
