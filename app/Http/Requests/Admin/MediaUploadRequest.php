<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Admin photo upload or URL import: the owner/credit and license must be recorded
 * (CLAUDE.md data rules), and the admin confirms they may use the image.
 */
class MediaUploadRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->isAdmin();
    }

    public function rules(): array
    {
        $fromUrl = $this->routeIs('admin.media.url');

        return [
            'photo' => [$fromUrl ? 'exclude' : 'required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:15360', 'dimensions:min_width=800'],
            'url' => [$fromUrl ? 'required' : 'exclude', 'url:http,https', 'max:2000'],
            'alt' => ['required', 'string', 'max:250'],
            'caption' => ['nullable', 'string', 'max:500'],
            'author' => ['required', 'string', 'max:250'],
            'license' => ['required', 'string', 'max:100'],
            'license_url' => ['nullable', 'url', 'max:500'],
            'rights' => ['accepted'],
        ];
    }

    public function messages(): array
    {
        return [
            'author.required' => 'Record who owns the photo (photographer or hotel).',
            'rights.accepted' => 'Confirm that you have the right to use this image.',
            'photo.dimensions' => 'The photo must be at least 800 pixels wide.',
        ];
    }

    /**
     * @return array{alt: string, author: string, license: string, license_url: ?string, caption: ?string}
     */
    public function meta(): array
    {
        return $this->safe()->only(['alt', 'author', 'license', 'license_url', 'caption']) + ['license_url' => null, 'caption' => null];
    }
}
