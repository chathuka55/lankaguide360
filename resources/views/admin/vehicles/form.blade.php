@php
    $editing = $vehicle->exists;
    $can = $editing ? auth()->user()->can('update', $vehicle) : true;
@endphp

<x-admin-layout :title="$editing ? $vehicle->type : 'Add vehicle'">
    <x-admin.header :title="$editing ? $vehicle->type : 'Add vehicle'" :back="route('admin.vehicles.index')" />
    <x-admin.readonly-note :can="$can" />

    <form method="POST" action="{{ $editing ? route('admin.vehicles.update', $vehicle) : route('admin.vehicles.store') }}" class="admin-card space-y-5 p-5">
        @csrf
        @if ($editing) @method('PUT') @endif
        <fieldset @disabled(! $can) class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
            <x-admin.input name="type" label="Vehicle type" :value="$vehicle->type" :required="true" maxlength="60" />
            <x-admin.input name="example_model" label="Example model" :value="$vehicle->example_model" maxlength="80" />
            <x-admin.select name="tier" label="Tier" :options="['budget' => 'Budget', 'premium' => 'Premium', 'luxury' => 'Luxury']" :value="$vehicle->tier" :required="true" />
            <x-admin.input name="luggage_capacity" label="Luggage (bags)" type="number" min="0" :value="$vehicle->luggage_capacity" />
            <x-admin.input name="min_pax" label="Min travellers" type="number" min="1" max="60" :value="$vehicle->min_pax" :required="true" />
            <x-admin.input name="max_pax" label="Max travellers" type="number" min="1" max="60" :value="$vehicle->max_pax" :required="true" />
            <x-admin.input name="day_rate" label="Day rate (USD)" type="number" step="0.01" min="0" :value="$vehicle->day_rate" :required="true" />
            <x-admin.input name="km_rate" label="Rate per km (USD)" type="number" step="0.01" min="0" :value="$vehicle->km_rate ?? '0.00'" :required="true" />
        </fieldset>
        <x-admin.form-actions :cancel="route('admin.vehicles.index')" :can="$can" />
    </form>
</x-admin-layout>
