<?php

namespace App\Http\Requests\Admin;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class UserRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->route('user');

        return $user ? $this->user()->can('update', $user) : $this->user()->can('create', User::class);
    }

    public function rules(): array
    {
        $creating = $this->route('user') === null;

        return [
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:190', Rule::unique('users', 'email')->ignore($this->route('user'))],
            'password' => [$creating ? 'required' : 'nullable', 'confirmed', Password::defaults()],
            'role' => ['required', Rule::enum(UserRole::class)],
            'phone' => ['nullable', 'string', 'max:30'],
            'country' => ['nullable', 'string', 'max:80'],
        ];
    }
}
