<p class="text-slate-600">Districts that match your interests are highlighted. Click them on the map or in the list.</p>

<div class="mt-6 grid gap-6 xl:grid-cols-2">
    <div wire:ignore wire:key="district-map-{{ implode('-', $categoryIds) }}" class="xl:sticky xl:top-24 xl:self-start"
         x-data="districtPicker({ geojsonUrl: @js(asset('geo/lk-districts.geojson')), districts: @js($mapDistricts), selected: @js($districtIds) })"
         @districts-changed.window="restyle($event.detail.ids)">
        <div x-ref="map" class="h-[28rem] overflow-hidden rounded-2xl border border-slate-200 bg-slate-100" role="application" aria-label="Map of Sri Lanka districts. Use the list to choose districts with the keyboard."></div>
        <p class="mt-2 flex flex-wrap gap-x-4 gap-y-1 text-xs text-slate-500">
            <span class="inline-flex items-center gap-1"><span class="size-3 rounded-sm bg-primary-700"></span> Chosen</span>
            <span class="inline-flex items-center gap-1"><span class="size-3 rounded-sm bg-accent-300"></span> Matches your interests</span>
            <span class="inline-flex items-center gap-1"><span class="size-3 rounded-sm bg-slate-200"></span> Not a match</span>
        </p>
    </div>

    <div class="space-y-5">
        @forelse ($byProvince as $province => $list)
            <div>
                <h2 class="text-sm font-semibold uppercase tracking-wide text-slate-500">{{ $province }}</h2>
                <div class="mt-2 grid gap-3 sm:grid-cols-2">
                    @foreach ($list as $district)
                        @php $on = in_array($district->id, $districtIds, true); $cover = $covers[$district->id] ?? null; @endphp
                        <button type="button" wire:click="toggleDistrict({{ $district->id }})" wire:key="district-{{ $district->id }}" aria-pressed="{{ $on ? 'true' : 'false' }}"
                                @class([
                                    'flex items-center gap-3 overflow-hidden rounded-xl border-2 bg-white p-2 text-start transition focus:outline-none focus-visible:ring-4 focus-visible:ring-primary-300',
                                    'border-primary-700 ring-2 ring-primary-100' => $on,
                                    'border-slate-200 hover:border-primary-300' => ! $on,
                                ])>
                            <span class="block size-14 shrink-0 overflow-hidden rounded-lg bg-primary-100">
                                @if ($cover)
                                    <x-media-img :media="$cover" :width="400" sizes="56px" alt="" class="size-full object-cover" />
                                @endif
                            </span>
                            <span class="min-w-0 flex-1">
                                <span class="block font-semibold">{{ $district->name }}</span>
                                <span class="block text-xs text-slate-500">{{ $district->places_count }} {{ Str::plural('place', $district->places_count) }}</span>
                            </span>
                            <span @class(['me-1 grid size-6 shrink-0 place-items-center rounded-full border', 'border-primary-700 bg-primary-700 text-white' => $on, 'border-slate-300' => ! $on])>
                                @if ($on)<x-icon name="check" class="size-3.5" />@endif
                            </span>
                        </button>
                    @endforeach
                </div>
            </div>
        @empty
            <p class="rounded-xl bg-slate-50 p-4 text-sm text-slate-600">No districts match these interests yet. Go back and pick another interest.</p>
        @endforelse
    </div>
</div>
@error('districtIds')<p class="mt-3 text-sm text-red-600" role="alert">{{ $message }}</p>@enderror
