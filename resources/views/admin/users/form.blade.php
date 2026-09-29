@php
    $editing = $user->exists;
    $canChangeRole = ! $editing || auth()->user()->can('changeRole', $user);
@endphp

<x-admin-layout :title="$editing ? $user->name : 'Add user'">
    <x-admin.header :title="$editing ? $user->name : 'Add user'" :back="route('admin.users.index')" />

    <form method="POST" action="{{ $editing ? route('admin.users.update', $user) : route('admin.users.store') }}" class="admin-card space-y-5 p-5">
        @csrf
        @if ($editing) @method('PUT') @endif
        <div class="grid gap-4 sm:grid-cols-2">
            <x-admin.input name="name" label="Name" :value="$user->name" :required="true" maxlength="120" />
            <x-admin.input name="email" label="Email" type="email" :value="$user->email" :required="true" maxlength="190" />
            <div>
                <x-admin.select name="role" label="Role" :options="collect(App\Enums\UserRole::cases())->mapWithKeys(fn ($r) => [$r->value => $r->label()])" :value="$user->role" :required="true" :disabled="! $canChangeRole" />
                @unless ($canChangeRole)
                    <input type="hidden" name="role" value="{{ $user->role->value }}">
                    <p class="mt-1 text-xs text-slate-500">You can't change your own role.</p>
                @endunless
            </div>
            <x-admin.input name="country" label="Country" :value="$user->country" maxlength="80" />
            <x-admin.input name="phone" label="Phone" :value="$user->phone" maxlength="30" />
        </div>
        <div class="grid gap-4 border-t border-slate-200 pt-4 sm:grid-cols-2">
            <x-admin.input name="password" label="{{ $editing ? 'New password (leave empty to keep)' : 'Password' }}" type="password" :required="! $editing" autocomplete="new-password" />
            <x-admin.input name="password_confirmation" label="Confirm password" type="password" :required="! $editing" autocomplete="new-password" />
        </div>
        <x-admin.form-actions :cancel="route('admin.users.index')" />
    </form>
</x-admin-layout>
