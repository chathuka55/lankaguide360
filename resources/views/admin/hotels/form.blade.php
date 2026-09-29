@php
    $editing = $hotel->exists;
    $can = $editing ? auth()->user()->can('update', $hotel) : true;
    $types = array_combine(App\Http\Requests\Admin\HotelRequest::TYPES, array_map(fn ($t) => str($t)->replace('_', ' ')->ucfirst()->toString(), App\Http\Requests\Admin\HotelRequest::TYPES));
@endphp

<x-admin-layout :title="$editing ? $hotel->name : 'Add hotel'">
    <x-admin.header :title="$editing ? $hotel->name : 'Add hotel'" :back="route('admin.hotels.index')" :subtitle="$editing ? $hotel->town.', '.$hotel->district?->name : null">
        @if ($editing)
            <x-admin.status-badge :status="$hotel->is_active ? $hotel->status : 'rejected'" class="text-sm" />
        @endif
    </x-admin.header>

    <x-admin.readonly-note :can="$can" />

    @if ($editing && $problems && $hotel->status->value === 'draft')
        <div class="mb-6 rounded-lg border border-accent-300 bg-accent-50 px-4 py-3 text-sm text-accent-700">Before publishing: {{ implode(', ', $problems) }}.</div>
    @endif

    <div class="grid gap-6 xl:grid-cols-3">
        <form method="POST" action="{{ $editing ? route('admin.hotels.update', $hotel) : route('admin.hotels.store') }}" class="space-y-6 xl:col-span-2">
            @csrf
            @if ($editing) @method('PUT') @endif

            <fieldset @disabled(! $can) class="space-y-6">
                <section class="admin-card space-y-4 p-5">
                    <h3 class="text-lg font-semibold">Details</h3>
                    <div class="grid gap-4 sm:grid-cols-2">
                        <x-admin.input name="name" label="Name" :value="$hotel->name" :required="true" maxlength="150" />
                        <x-admin.input name="slug" label="URL slug" :value="$hotel->slug" maxlength="170" help="Leave empty to create it from the name and town." />
                        <x-admin.select name="district_id" label="District" :options="$districts" :value="$hotel->district_id" :required="true" placeholder="Choose…" />
                        <x-admin.input name="town" label="Town" :value="$hotel->town" :required="true" maxlength="80" help="Overnight town used by the itinerary engine." />
                        <x-admin.select name="type" label="Type" :options="$types" :value="$hotel->type" placeholder="—" />
                        <x-admin.select name="tier" label="Tier" :options="['budget' => 'Budget', 'premium' => 'Premium', 'luxury' => 'Luxury']" :value="$hotel->tier" :required="true" help="Imported hotels get a guess from their stars; please check it." />
                        <x-admin.select name="star_rating" label="Stars" :options="[1 => '1★', 2 => '2★', 3 => '3★', 4 => '4★', 5 => '5★']" :value="$hotel->star_rating" placeholder="Not rated" />
                        <x-admin.select name="status" label="Status" :options="['draft' => 'Draft', 'published' => 'Published']" :value="$hotel->status ?? 'draft'" :required="true" />
                    </div>
                    <x-admin.tag-input name="amenities" label="Amenities" :tags="$hotel->amenities ?? []" :suggestions="config('lankaguide.amenities')" :readonly="! $can" help="Pool and beach front are Trip Builder filters." />
                    <div class="flex flex-wrap gap-6">
                        <x-admin.checkbox name="kid_friendly" label="Kid-friendly" :checked="(bool) $hotel->kid_friendly" help="Shown first when a trip has children." />
                        <x-admin.checkbox name="is_active" label="Active" :checked="$hotel->exists ? (bool) $hotel->is_active : true" />
                    </div>
                </section>

                <section class="admin-card space-y-4 p-5">
                    <h3 class="text-lg font-semibold">Contact</h3>
                    <div class="grid gap-4 sm:grid-cols-2">
                        <x-admin.input name="website" label="Website" type="url" :value="$hotel->website" />
                        <x-admin.input name="phone" label="Phone" :value="$hotel->phone" maxlength="60" />
                        <div class="sm:col-span-2"><x-admin.input name="address" label="Address" :value="$hotel->address" maxlength="255" /></div>
                        <x-admin.input name="wikipedia_title" label="Wikipedia article title" :value="$hotel->wikipedia_title" maxlength="200" />
                    </div>
                    @if ($hotel->osm_id)
                        <p class="text-sm text-slate-600">OpenStreetMap: <a href="https://www.openstreetmap.org/{{ $hotel->osm_id }}" target="_blank" rel="noopener" class="text-primary-700 hover:underline">{{ $hotel->osm_id }}</a> (© OpenStreetMap contributors, ODbL)</p>
                    @endif
                </section>

                <section class="admin-card space-y-4 p-5">
                    <h3 class="text-lg font-semibold">Location</h3>
                    <x-admin.map-picker :lat="$hotel->lat" :lng="$hotel->lng" :readonly="! $can" />
                </section>
            </fieldset>

            <x-admin.form-actions :cancel="route('admin.hotels.index')" :can="$can" :label="$editing ? 'Save hotel' : 'Create hotel'" />
        </form>

        @if ($editing)
            <aside class="space-y-6">
                @can('delete', $hotel)
                    <section class="admin-card space-y-3 p-5">
                        <h3 class="font-semibold">Quick actions</h3>
                        <div class="flex flex-wrap gap-2">
                            <form method="POST" action="{{ route('admin.hotels.bulk') }}">
                                @csrf
                                <input type="hidden" name="ids[]" value="{{ $hotel->id }}">
                                <input type="hidden" name="action" value="{{ $hotel->status->value === 'draft' ? 'publish_with_photos' : 'unpublish' }}">
                                <button type="submit" class="btn {{ $hotel->status->value === 'draft' ? 'btn-primary' : 'btn-secondary' }} btn-sm" @disabled($hotel->status->value === 'draft' && $problems)>
                                    {{ $hotel->status->value === 'draft' ? 'Publish with photos' : 'Unpublish' }}
                                </button>
                            </form>
                            <x-admin.delete-form :action="route('admin.hotels.destroy', $hotel)" label="Delete hotel" :confirm="'Delete '.$hotel->name.', its rates and photos?'" button="btn btn-danger btn-sm" />
                        </div>
                    </section>
                @endcan
            </aside>
        @endif
    </div>

    @if ($editing)
        {{-- Room rates --}}
        <section id="rates" class="admin-card mt-6 scroll-mt-20 p-5" aria-labelledby="rates-heading">
            <h3 id="rates-heading" class="text-lg font-semibold">Room rates</h3>
            <p class="mt-1 text-sm text-slate-600">Prices per room per night in USD. No free source has live hotel prices, so enter the agreed rate bands. Leave the season empty for all year.</p>

            <div class="mt-4 overflow-x-auto">
                <table class="admin-table">
                    <thead>
                        <tr><th>Room</th><th>Meals</th><th>Price / night</th><th>Guests</th><th>Extra bed</th><th>Season</th><th>Estimate</th><th><span class="sr-only">Actions</span></th></tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @forelse ($hotel->roomRates as $rate)
                            @if ($can)
                                <tr>
                                    <td colspan="8" class="p-0">
                                        <form method="POST" action="{{ route('admin.rates.update', $rate) }}" class="grid grid-cols-2 items-end gap-2 p-3 md:grid-cols-8">
                                            @csrf
                                            @method('PUT')
                                            <label class="text-xs"><span class="sr-only">Room type</span><input name="room_type" value="{{ $rate->room_type }}" required maxlength="60" class="form-control"></label>
                                            <label class="text-xs"><span class="sr-only">Meal plan</span>
                                                <select name="meal_plan" class="form-control">@foreach ($mealPlans as $value => $label)<option value="{{ $value }}" @selected($rate->meal_plan->value === $value)>{{ $value }}</option>@endforeach</select>
                                            </label>
                                            <label class="text-xs"><span class="sr-only">Price per night</span><input name="price_per_night" type="number" step="0.01" min="0" value="{{ $rate->price_per_night }}" required class="form-control"></label>
                                            <label class="text-xs"><span class="sr-only">Max guests</span><input name="max_occupancy" type="number" min="1" max="10" value="{{ $rate->max_occupancy }}" required class="form-control"></label>
                                            <label class="text-xs"><span class="sr-only">Extra bed price</span><input name="extra_bed_price" type="number" step="0.01" min="0" value="{{ $rate->extra_bed_price }}" class="form-control"></label>
                                            <div class="flex gap-1">
                                                <label class="text-xs"><span class="sr-only">Season from</span><input name="season_from" type="date" value="{{ $rate->season_from?->format('Y-m-d') }}" class="form-control px-1"></label>
                                                <label class="text-xs"><span class="sr-only">Season to</span><input name="season_to" type="date" value="{{ $rate->season_to?->format('Y-m-d') }}" class="form-control px-1"></label>
                                            </div>
                                            <label class="inline-flex items-center gap-1 text-xs"><input type="hidden" name="is_estimate" value="0"><input type="checkbox" name="is_estimate" value="1" @checked($rate->is_estimate) class="form-check"> Estimate</label>
                                            <div class="flex items-center justify-end gap-2">
                                                <button type="submit" class="btn btn-secondary btn-sm">Save</button>
                                            </div>
                                        </form>
                                        <div class="flex justify-end px-3 pb-2">
                                            <x-admin.delete-form :action="route('admin.rates.destroy', $rate)" label="Delete rate" confirm="Delete this room rate?" button="btn-link-danger text-xs" />
                                        </div>
                                    </td>
                                </tr>
                            @else
                                <tr>
                                    <td>{{ $rate->room_type }}</td>
                                    <td>{{ $rate->meal_plan->label() }}</td>
                                    <td>${{ $rate->price_per_night }}</td>
                                    <td>{{ $rate->max_occupancy }}</td>
                                    <td>${{ $rate->extra_bed_price }}</td>
                                    <td class="text-xs">{{ $rate->season_from?->format('d M Y') ?? 'All year' }}@if ($rate->season_to) – {{ $rate->season_to->format('d M Y') }}@endif</td>
                                    <td>{{ $rate->is_estimate ? 'Yes' : 'No' }}</td>
                                    <td></td>
                                </tr>
                            @endif
                        @empty
                            <tr><td colspan="8"><x-admin.empty message="No room rates yet. The Trip Builder needs at least one rate to price this hotel." /></td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            @if ($can)
                <form method="POST" action="{{ route('admin.hotels.rates.store', $hotel) }}" class="mt-4 rounded-lg border border-dashed border-slate-300 p-4">
                    @csrf
                    <h4 class="mb-3 text-sm font-semibold">Add a room rate</h4>
                    <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                        <x-admin.input name="room_type" label="Room type" :required="true" placeholder="Deluxe double" maxlength="60" />
                        <x-admin.select name="meal_plan" label="Meal plan" :options="$mealPlans" value="BB" :required="true" />
                        <x-admin.input name="price_per_night" label="Price per night (USD)" type="number" step="0.01" min="0" :required="true" />
                        <x-admin.input name="max_occupancy" label="Max guests" type="number" min="1" max="10" value="2" :required="true" />
                        <x-admin.input name="extra_bed_price" label="Extra bed (USD)" type="number" step="0.01" min="0" value="0" />
                        <x-admin.input name="season_from" label="Season from" type="date" />
                        <x-admin.input name="season_to" label="Season to" type="date" />
                        <div class="flex items-end pb-2"><x-admin.checkbox name="is_estimate" label="Estimate only" /></div>
                    </div>
                    <div class="mt-3 flex justify-end"><button type="submit" class="btn btn-primary">Add rate</button></div>
                </form>
            @endif
        </section>

        <div class="mt-6">
            <x-admin.media-manager :owner="$hotel" />
        </div>
    @endif
</x-admin-layout>
