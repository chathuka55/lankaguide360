@php
    $editing = $place->exists;
    $can = $editing ? auth()->user()->can('update', $place) : true;
    $selectedCategories = collect(old('categories', $place->categories->pluck('id')->all()))->map(fn ($id) => (int) $id)->all();
@endphp

<x-admin-layout :title="$editing ? $place->name : 'Add place'">
    <x-admin.header :title="$editing ? $place->name : 'Add place'" :back="route('admin.places.index')"
                    :subtitle="$editing ? $place->district?->name.' · '.($place->source === 'osm' ? 'Suggested from OpenStreetMap' : ($place->source === 'seed' ? 'Starter list' : 'Added by admin')) : null">
        @if ($editing)
            <x-admin.status-badge :status="$place->is_active ? $place->status : 'rejected'" class="text-sm" />
        @endif
    </x-admin.header>

    <x-admin.readonly-note :can="$can" />

    @if ($editing && $problems && $place->status->value === 'draft')
        <div class="mb-6 rounded-lg border border-accent-300 bg-accent-50 px-4 py-3 text-sm text-accent-700">
            Before publishing: {{ implode(', ', $problems) }}.
        </div>
    @endif

    <div class="grid gap-6 xl:grid-cols-3">
        <form method="POST" action="{{ $editing ? route('admin.places.update', $place) : route('admin.places.store') }}" class="space-y-6 xl:col-span-2">
            @csrf
            @if ($editing) @method('PUT') @endif

            <fieldset @disabled(! $can) class="space-y-6">
                <section class="admin-card space-y-4 p-5">
                    <h3 class="text-lg font-semibold">Details</h3>
                    <div class="grid gap-4 sm:grid-cols-2">
                        <x-admin.input name="name" label="Name" :value="$place->name" :required="true" maxlength="150" />
                        <x-admin.input name="slug" label="URL slug" :value="$place->slug" maxlength="170" help="Leave empty to create it from the name." />
                        <x-admin.select name="district_id" label="District" :options="$districts" :value="$place->district_id" :required="true" placeholder="Choose…" />
                        <x-admin.select name="status" label="Status" :options="['draft' => 'Draft', 'published' => 'Published']" :value="$place->status ?? 'draft'" :required="true" help="Publishing needs map coordinates and a category." />
                    </div>

                    <fieldset>
                        <legend class="form-label">Categories</legend>
                        <div class="grid gap-2 sm:grid-cols-3">
                            @foreach ($categories as $category)
                                <label class="inline-flex items-center gap-2 text-sm">
                                    <input type="checkbox" name="categories[]" value="{{ $category->id }}" @checked(in_array($category->id, $selectedCategories, true)) class="form-check">
                                    {{ $category->name }}
                                </label>
                            @endforeach
                        </div>
                        @error('categories')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                    </fieldset>

                    <x-admin.textarea name="short_description" label="Short description" :value="$place->short_description" rows="2" maxlength="300" help="One or two sentences for cards (max 300 characters)." />
                    <x-admin.textarea name="description" label="Description" :value="$place->description" rows="6" />
                </section>

                <section class="admin-card space-y-4 p-5">
                    <h3 class="text-lg font-semibold">Location</h3>
                    <x-admin.map-picker :lat="$place->lat" :lng="$place->lng" :readonly="! $can" />
                </section>

                <section class="admin-card space-y-4 p-5">
                    <h3 class="text-lg font-semibold">Visiting</h3>
                    <div class="grid gap-4 sm:grid-cols-3">
                        <x-admin.input name="visit_minutes" label="Time needed (minutes)" type="number" min="10" max="720" :value="$place->visit_minutes ?? 90" :required="true" />
                        <x-admin.input name="open_time" label="Opens" type="time" :value="$place->open_time ? substr($place->open_time, 0, 5) : null" />
                        <x-admin.input name="close_time" label="Closes" type="time" :value="$place->close_time ? substr($place->close_time, 0, 5) : null" />
                        <x-admin.input name="fee_foreign_adult" label="Entry fee, foreign adult (USD)" type="number" step="0.01" min="0" :value="$place->fee_foreign_adult ?? '0.00'" :required="true" />
                        <x-admin.input name="fee_foreign_child" label="Entry fee, foreign child (USD)" type="number" step="0.01" min="0" :value="$place->fee_foreign_child ?? '0.00'" :required="true" />
                        <x-admin.input name="best_months" label="Best months" :value="$place->best_months" maxlength="40" placeholder="e.g. Dec–Apr" />
                        <x-admin.select name="best_time_slot" label="Best time of day" :options="collect(App\Enums\BestTimeSlot::cases())->mapWithKeys(fn ($c) => [$c->value => ucfirst($c->value)])" :value="$place->best_time_slot ?? 'any'" :required="true" />
                        <x-admin.select name="crowd_level" label="Crowd level" :options="['low' => 'Low', 'medium' => 'Medium', 'high' => 'High']" :value="$place->crowd_level ?? 'medium'" :required="true" />
                    </div>
                    <div class="flex flex-wrap gap-6">
                        <x-admin.checkbox name="is_hidden_gem" label="Hidden gem" :checked="(bool) $place->is_hidden_gem" help="Low tourist traffic; shown in the Hidden Gems category." />
                        <x-admin.checkbox name="is_active" label="Active" :checked="$place->exists ? (bool) $place->is_active : true" help="Untick to hide a rejected place without deleting it." />
                    </div>
                </section>

                <section class="admin-card space-y-4 p-5">
                    <h3 class="text-lg font-semibold">Source</h3>
                    <div class="grid gap-4 sm:grid-cols-2">
                        <x-admin.input name="wikipedia_title" label="Wikipedia article title" :value="$place->wikipedia_title" maxlength="200" help="Exact English title; re-run lg:import-places to fill text and coordinates." />
                        <x-admin.input name="wikipedia_url" label="Wikipedia URL" type="url" :value="$place->wikipedia_url" />
                    </div>
                    @if ($place->osm_id)
                        <p class="text-sm text-slate-600">OpenStreetMap: <a href="https://www.openstreetmap.org/{{ $place->osm_id }}" target="_blank" rel="noopener" class="text-primary-700 hover:underline">{{ $place->osm_id }}</a></p>
                    @endif
                </section>
            </fieldset>

            <x-admin.form-actions :cancel="route('admin.places.index')" :can="$can" :label="$editing ? 'Save place' : 'Create place'" />
        </form>

        <aside class="space-y-6">
            @if ($editing)
                @can('delete', $place)
                    <section class="admin-card space-y-3 p-5">
                        <h3 class="font-semibold">Quick actions</h3>
                        <div class="flex flex-wrap gap-2">
                            @if ($place->status->value === 'draft')
                                <form method="POST" action="{{ route('admin.places.bulk') }}">
                                    @csrf
                                    <input type="hidden" name="ids[]" value="{{ $place->id }}">
                                    <input type="hidden" name="action" value="publish_with_photos">
                                    <button type="submit" class="btn btn-primary btn-sm" @disabled($problems)>Publish with photos</button>
                                </form>
                            @else
                                <form method="POST" action="{{ route('admin.places.bulk') }}">
                                    @csrf
                                    <input type="hidden" name="ids[]" value="{{ $place->id }}">
                                    <input type="hidden" name="action" value="unpublish">
                                    <button type="submit" class="btn btn-secondary btn-sm">Unpublish</button>
                                </form>
                            @endif
                            <x-admin.delete-form :action="route('admin.places.destroy', $place)" label="Delete place" :confirm="'Delete '.$place->name.' and its photos?'" button="btn btn-danger btn-sm" />
                        </div>
                    </section>
                @endcan

                @if ($place->wikipedia_url)
                    <section class="admin-card p-5 text-sm">
                        <h3 class="font-semibold">Attribution</h3>
                        <p class="mt-1 text-slate-600">Text from <a href="{{ $place->wikipedia_url }}" target="_blank" rel="noopener" class="text-primary-700 hover:underline">Wikipedia</a>, CC BY-SA 4.0.</p>
                    </section>
                @endif

                @if ($logs->isNotEmpty())
                    <section class="admin-card p-5">
                        <h3 class="font-semibold">Import history</h3>
                        <ul class="mt-3 space-y-3 text-xs">
                            @foreach ($logs as $log)
                                <li>
                                    <div class="flex items-center gap-2">
                                        <x-admin.status-badge :status="$log->status" />
                                        <span class="text-slate-500">{{ $log->importer }} · {{ $log->created_at?->diffForHumans() }}</span>
                                    </div>
                                    <p class="mt-1 text-slate-600">{{ $log->message }}</p>
                                </li>
                            @endforeach
                        </ul>
                    </section>
                @endif
            @endif
        </aside>
    </div>

    @if ($editing)
        <div class="mt-6">
            <x-admin.media-manager :owner="$place" />
        </div>
    @endif
</x-admin-layout>
