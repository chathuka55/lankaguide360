<?php

namespace App\Http\Requests\Admin;

use App\Http\Controllers\Admin\SettingController;
use Illuminate\Foundation\Http\FormRequest;

class SettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->isAdmin();
    }

    public function rules(): array
    {
        return collect(SettingController::FIELDS)->collapse()
            ->mapWithKeys(fn (array $field, string $key) => ["settings.{$key}" => $field[2]])
            ->all();
    }

    public function attributes(): array
    {
        return collect(SettingController::FIELDS)->collapse()
            ->mapWithKeys(fn (array $field, string $key) => ["settings.{$key}" => $field[0]])
            ->all();
    }
}
