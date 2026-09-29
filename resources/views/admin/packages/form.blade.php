@php
    $editing = $package->exists;
    $can = $editing ? auth()->user()->can('update', $package) : true;
@endphp

<x-admin-layout :title="$editing ? $package->name : 'Add package'">
    <x-admin.header :title="$editing ? $package->name : 'Add package'" :back="route('admin.packages.index')" />
    <x-admin.readonly-note :can="$can" />

    @if (! $editing && empty($trips))
        <div class="admin-card p-5 text-sm text-slate-600">
            A package needs a template trip, and there are no trips yet. Open a trip request and use “Save as package”, or run <code>php artisan db:seed --class=PackageSeeder</code>.
        </div>
    @else
        <form method="POST" action="{{ $editing ? route('admin.packages.update', $package) : route('admin.packages.store') }}" class="admin-card space-y-5 p-5">
            @csrf
            @if ($editing) @method('PUT') @endif
            <fieldset @disabled(! $can) class="space-y-5">
                <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                    <x-admin.input name="name" label="Name" :value="$package->name" :required="true" maxlength="150" />
                    <x-admin.input name="slug" label="URL slug" :value="$package->slug" maxlength="170" />
                    <x-admin.select name="template_trip_id" label="Template trip" :options="$trips" :value="$package->template_trip_id" :required="true" placeholder="Choose…" />
                    <x-admin.select name="tier" label="Tier" :options="['budget' => 'Budget', 'premium' => 'Premium', 'luxury' => 'Luxury']" :value="$package->tier" placeholder="—" />
                    <x-admin.input name="days" label="Days" type="number" min="1" max="21" :value="$package->days" />
                    <x-admin.input name="from_price" label="From price per person (USD)" type="number" step="0.01" min="0" :value="$package->from_price" />
                    <x-admin.input name="sort_order" label="Sort order" type="number" :value="$package->sort_order" />
                    <x-admin.input name="cover_image" label="Cover image path" :value="$package->cover_image" />
                </div>
                <x-admin.textarea name="summary" label="Summary" :value="$package->summary" rows="2" maxlength="300" />
                <div class="grid gap-4 sm:grid-cols-2">
                    <x-admin.textarea name="inclusions" label="Inclusions (one per line)" :value="$package->inclusions" rows="5" />
                    <x-admin.textarea name="exclusions" label="Exclusions (one per line)" :value="$package->exclusions" rows="5" />
                </div>
                <x-admin.checkbox name="is_featured" label="Featured on the Home page" :checked="(bool) $package->is_featured" />
            </fieldset>
            <x-admin.form-actions :cancel="route('admin.packages.index')" :can="$can" />
        </form>
    @endif
</x-admin-layout>
