<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Bulk publish / unpublish / reject for places and hotels (admin only).
 */
class BulkActionRequest extends FormRequest
{
    public const ACTIONS = ['publish', 'publish_with_photos', 'unpublish', 'reject', 'restore'];

    public function authorize(): bool
    {
        return $this->user()->isAdmin();
    }

    public function rules(): array
    {
        return [
            'action' => ['required', Rule::in(self::ACTIONS)],
            'ids' => ['required', 'array', 'min:1', 'max:500'],
            'ids.*' => ['integer'],
        ];
    }

    public function messages(): array
    {
        return ['ids.required' => 'Tick at least one row first.'];
    }
}
