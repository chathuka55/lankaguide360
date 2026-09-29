@php
    $can = auth()->user()->can('update', $district);
    $selected = collect(old('categories', $district->categories->pluck('id')->all()))->map(fn ($id) => (int) $id)->all();
@endphp

<x-admin-layout :title="$district->name">
    <x-admin.header :title="$district->name.' District'" :subtitle="$district->province->name" :back="route('admin.districts.index')" />
    <x-admin.readonly-note :can="$can" />

    <form method="POST" action="{{ route('admin.districts.update', $district) }}" class="space-y-6">
        @csrf
        @method('PUT')
        <fieldset @disabled(! $can) class="space-y-6">
            <section class="admin-card space-y-4 p-5">
                <fieldset>
                    <legend class="form-label">Categories shown for this district in the Trip Builder</legend>
                    <div class="grid gap-2 sm:grid-cols-3">
                        @foreach ($categories as $category)
                            <label class="inline-flex items-center gap-2 text-sm">
                                <input type="checkbox" name="categories[]" value="{{ $category->id }}" @checked(in_array($category->id, $selected, true)) class="form-check"> {{ $category->name }}
                            </label>
                        @endforeach
                    </div>
                </fieldset>
                <x-admin.textarea name="description" label="Introduction" :value="$district->description" rows="5" />
                <x-admin.input name="image" label="Image path" :value="$district->image" help="Optional; phase 5 uses the cover photo of a top place if empty." />
            </section>
            <section class="admin-card space-y-4 p-5">
                <h3 class="text-lg font-semibold">Centre point</h3>
                <p class="text-sm text-slate-600">Used to order districts when building a route. Set automatically by lg:import-districts.</p>
                <x-admin.map-picker :lat="$district->lat" :lng="$district->lng" :readonly="! $can" :required="true" />
            </section>
        </fieldset>
        <x-admin.form-actions :cancel="route('admin.districts.index')" :can="$can" label="Save district" />
    </form>
</x-admin-layout>
