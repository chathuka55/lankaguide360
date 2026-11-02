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

        <fieldset @disabled(! $can) class="space-y-4 border-t border-slate-200 pt-5">
            <legend class="sr-only">Services and hire</legend>
            <x-admin.textarea name="description" label="Description" :value="$vehicle->description" rows="2" help="Shown on the public Vehicle hire page." />
            <div>
                <span class="form-label">Services on board</span>
                <div class="grid gap-2 sm:grid-cols-3">
                    @foreach (App\Models\Vehicle::AMENITIES as $key => $label)
                        <label class="flex items-center gap-2 text-sm">
                            <input type="checkbox" name="amenities[]" value="{{ $key }}" @checked(in_array($key, old('amenities', $vehicle->amenities ?? []), true)) class="form-check">
                            {{ $label }}
                        </label>
                    @endforeach
                </div>
            </div>
            <label class="flex items-center gap-2 text-sm">
                <input type="hidden" name="is_rentable" value="0">
                <input type="checkbox" name="is_rentable" value="1" @checked(old('is_rentable', $vehicle->is_rentable ?? true)) class="form-check">
                Show on the public Vehicle hire page
            </label>
            <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                <x-admin.input name="provider_name" label="Provider / company" :value="$vehicle->provider_name" maxlength="120" help="Empty = LankaGuide360" />
                <x-admin.input name="provider_phone" label="Provider phone" :value="$vehicle->provider_phone" maxlength="30" />
                <x-admin.input name="provider_email" label="Provider email" type="email" :value="$vehicle->provider_email" maxlength="190" />
                <x-admin.input name="provider_website" label="Provider website / booking link" type="url" :value="$vehicle->provider_website" maxlength="255" />
            </div>
        </fieldset>

        <fieldset @disabled(! $can) class="space-y-4 border-t border-slate-200 pt-5">
            <legend class="text-sm font-semibold text-slate-900">Self-drive</legend>
            <label class="flex items-center gap-2 text-sm">
                <input type="hidden" name="self_drive" value="0">
                <input type="checkbox" name="self_drive" value="1" @checked(old('self_drive', $vehicle->self_drive ?? false)) class="form-check">
                Can be hired without a driver (Sri Lankan residents with a Sri Lankan licence only)
            </label>
            <div class="grid gap-4 sm:grid-cols-3">
                <x-admin.input name="self_drive_day_rate" label="Self-drive day rate (USD)" type="number" step="0.01" min="0" :value="$vehicle->self_drive_day_rate" help="No driver, fuel not included." />
                <x-admin.input name="self_drive_deposit" label="Refundable deposit (USD)" type="number" step="0.01" min="0" :value="$vehicle->self_drive_deposit" />
                <x-admin.input name="self_drive_km_per_day" label="Km included per day" type="number" min="0" :value="$vehicle->self_drive_km_per_day" help="Extra km are charged at the rate per km." />
            </div>
        </fieldset>
        <x-admin.form-actions :cancel="route('admin.vehicles.index')" :can="$can" />
    </form>

    @if ($editing)
        <section class="mt-6">
            <h3 class="mb-3 text-lg font-semibold">Photos</h3>
            <x-admin.media-manager :owner="$vehicle" />
        </section>
    @endif
</x-admin-layout>
