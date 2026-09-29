@php
    $editing = $category->exists;
    $can = $editing ? auth()->user()->can('update', $category) : true;
    $selected = collect(old('districts', $editing ? $category->districts->pluck('id')->all() : []))->map(fn ($id) => (int) $id)->all();
@endphp

<x-admin-layout :title="$editing ? $category->name : 'Add category'">
    <x-admin.header :title="$editing ? $category->name : 'Add category'" :back="route('admin.categories.index')" />
    <x-admin.readonly-note :can="$can" />

    <form method="POST" action="{{ $editing ? route('admin.categories.update', $category) : route('admin.categories.store') }}" class="admin-card space-y-5 p-5">
        @csrf
        @if ($editing) @method('PUT') @endif
        <fieldset @disabled(! $can) class="space-y-5">
            <div class="grid gap-4 sm:grid-cols-3">
                <x-admin.input name="name" label="Name" :value="$category->name" :required="true" maxlength="60" />
                <x-admin.input name="slug" label="URL slug" :value="$category->slug" maxlength="80" help="Used in links like /plan?category=…" />
                <x-admin.input name="icon" label="Icon name" :value="$category->icon" maxlength="60" />
            </div>
            <fieldset>
                <legend class="form-label">Districts that show this category</legend>
                <div class="grid gap-2 sm:grid-cols-3 lg:grid-cols-5">
                    @foreach ($districts as $district)
                        <label class="inline-flex items-center gap-2 text-sm">
                            <input type="checkbox" name="districts[]" value="{{ $district->id }}" @checked(in_array($district->id, $selected, true)) class="form-check"> {{ $district->name }}
                        </label>
                    @endforeach
                </div>
            </fieldset>
        </fieldset>
        <x-admin.form-actions :cancel="route('admin.categories.index')" :can="$can" />
    </form>
</x-admin-layout>
