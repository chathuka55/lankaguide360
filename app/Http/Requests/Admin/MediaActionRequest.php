<?php

namespace App\Http\Requests\Admin;

use App\Enums\MediaStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validation for the small photo actions (admin only): edit details, bulk publish/reject,
 * reorder, Commons import and Commons search. Rules depend on the route.
 */
class MediaActionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->isAdmin();
    }

    public function rules(): array
    {
        return match ($this->route()?->getName()) {
            'admin.media.update' => [
                'alt' => ['required', 'string', 'max:250'],
                'caption' => ['nullable', 'string', 'max:500'],
                'status' => ['required', Rule::in([MediaStatus::Draft->value, MediaStatus::Published->value])],
            ],
            'admin.media.bulk' => [
                'action' => ['required', Rule::in(['publish', 'reject'])],
                'ids' => ['required', 'array', 'min:1', 'max:200'],
                'ids.*' => ['integer'],
            ],
            'admin.media.reorder' => [
                'ids' => ['required', 'array'],
                'ids.*' => ['integer'],
            ],
            'admin.media.commons' => [
                'titles' => ['required', 'array', 'min:1', 'max:10'],
                'titles.*' => ['string', 'starts_with:File:', 'max:255'],
            ],
            'admin.media.commons-search' => [
                'q' => ['required', 'string', 'max:200'],
            ],
            default => [],
        };
    }

    public function messages(): array
    {
        return [
            'ids.required' => 'Tick at least one photo first.',
            'titles.required' => 'Tick at least one photo to import.',
        ];
    }
}
