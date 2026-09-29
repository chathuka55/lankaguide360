@php
    $editing = $guide->exists;
    $can = $editing ? auth()->user()->can('update', $guide) : true;
@endphp

<x-admin-layout :title="$editing ? $guide->name : 'Add guide'">
    <x-admin.header :title="$editing ? $guide->name : 'Add guide'" :back="route('admin.guides.index')" />
    <x-admin.readonly-note :can="$can" />

    <form method="POST" action="{{ $editing ? route('admin.guides.update', $guide) : route('admin.guides.store') }}" class="admin-card space-y-5 p-5">
        @csrf
        @if ($editing) @method('PUT') @endif
        <fieldset @disabled(! $can) class="space-y-5">
            <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                <x-admin.input name="name" label="Name" :value="$guide->name" :required="true" maxlength="120" />
                <x-admin.select name="type" label="Type" :options="['chauffeur' => 'Chauffeur-guide', 'national' => 'SLTDA national guide', 'site' => 'Site guide']" :value="$guide->type" :required="true" />
                <x-admin.input name="day_rate" label="Day rate (USD)" type="number" step="0.01" min="0" :value="$guide->day_rate" :required="true" />
                <x-admin.input name="phone" label="Phone" :value="$guide->phone" maxlength="30" />
            </div>
            <x-admin.tag-input name="languages" label="Languages" :tags="$guide->languages ?? []" :suggestions="['english', 'german', 'french', 'chinese', 'russian', 'japanese', 'spanish', 'italian', 'hindi', 'tamil', 'sinhala']" :readonly="! $can" />
            <x-admin.checkbox name="is_available" label="Available for new trips" :checked="(bool) $guide->is_available" />
        </fieldset>
        <x-admin.form-actions :cancel="route('admin.guides.index')" :can="$can" />
    </form>
</x-admin-layout>
