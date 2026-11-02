<div class="flex flex-col gap-3 rounded-2xl bg-primary-50 p-4 sm:flex-row sm:items-center sm:justify-between">
    <p class="text-primary-900">
        <span class="text-2xl font-extrabold">{{ count($placeIds) }}</span> {{ Str::plural('place', count($placeIds)) }} chosen
        @if ($recommended)
            <span class="text-primary-800">≈ <strong>{{ $recommended }} {{ Str::plural('day', $recommended) }}</strong> recommended</span>
        @endif
    </p>
    <p class="text-xs text-primary-800">Based on visit times plus driving, at your travel style's pace.</p>
</div>

{{-- Everything picked so far, across districts: one tap removes a place --}}
@if ($pickedPlaces->isNotEmpty())
    <div class="mt-4 rounded-2xl border border-slate-200 bg-white p-4">
        <p class="text-sm font-semibold">Your places</p>
        <ul class="mt-2 flex flex-wrap gap-2" role="list">
            @foreach ($pickedPlaces as $picked)
                <li wire:key="picked-{{ $picked->id }}">
                    <button type="button" wire:click="togglePlace({{ $picked->id }})" class="group inline-flex items-center gap-1.5 rounded-full bg-primary-50 py-1 ps-3 pe-2 text-sm text-primary-900 ring-1 ring-primary-200 hover:bg-red-50 hover:text-red-800 hover:ring-red-200"
                            aria-label="Remove {{ $picked->name }} from your trip">
                        {{ $picked->name }} <span class="text-xs text-primary-700 group-hover:text-red-700">· {{ $picked->district?->name }}</span>
                        <x-icon name="x" class="size-3.5" />
                    </button>
                </li>
            @endforeach
        </ul>
    </div>
@endif

{{-- District tabs --}}
<div class="mt-5 flex gap-2 overflow-x-auto pb-1" role="tablist" aria-label="Districts">
    @foreach ($placeDistricts as $district)
        @php $active = $activeDistrict?->is($district); @endphp
        <button type="button" role="tab" aria-selected="{{ $active ? 'true' : 'false' }}" wire:click="showDistrict({{ $district->id }})" wire:key="tab-{{ $district->id }}"
                @class(['whitespace-nowrap rounded-full px-4 py-2 text-sm font-semibold transition', 'bg-slate-900 text-white' => $active, 'bg-white text-slate-700 ring-1 ring-slate-200 hover:bg-slate-50' => ! $active])>
            {{ $district->name }}
            @if ($pickedPerDistrict[$district->id] ?? 0)
                <span @class(['ms-1 rounded-full px-1.5 text-xs', 'bg-white/20' => $active, 'bg-primary-100 text-primary-800' => ! $active])>{{ $pickedPerDistrict[$district->id] }}</span>
            @else
                <span class="ms-1 inline-block size-2 rounded-full bg-accent-500 align-middle" title="No places added here yet"><span class="sr-only">(no places yet)</span></span>
            @endif
        </button>
    @endforeach
</div>

{{-- Category filter --}}
<div class="mt-3 flex flex-wrap items-center gap-2 text-sm">
    <span class="text-slate-500">Show:</span>
    <button type="button" wire:click="filterPlaces(null)" @class(['rounded-full px-3 py-1', 'bg-primary-700 text-white' => ! $placeCategoryFilter, 'bg-slate-100 text-slate-700 hover:bg-slate-200' => $placeCategoryFilter])>All</button>
    @foreach ($filterCategories as $category)
        <button type="button" wire:click="filterPlaces({{ $category->id }})" wire:key="filter-{{ $category->id }}"
                @class(['rounded-full px-3 py-1', 'bg-primary-700 text-white' => $placeCategoryFilter === $category->id, 'bg-slate-100 text-slate-700 hover:bg-slate-200' => $placeCategoryFilter !== $category->id])>{{ $category->name }}</button>
    @endforeach
</div>

<div class="mt-5 grid gap-4 sm:grid-cols-2 2xl:grid-cols-3" role="tabpanel">
    @forelse ($places as $place)
        @php $on = in_array($place->id, $placeIds, true); @endphp
        <article wire:key="place-{{ $place->id }}" @class(['flex flex-col overflow-hidden rounded-2xl border-2 bg-white transition', 'border-primary-700 shadow-md' => $on, 'border-slate-200' => ! $on])>
            <div class="relative aspect-[16/10] bg-primary-100">
                @if ($place->cover)
                    <x-media-img :media="$place->cover" :width="800" sizes="(min-width: 1536px) 22vw, (min-width: 640px) 40vw, 100vw" :alt="$place->name" class="size-full object-cover" />
                @endif
                @if ($place->is_hidden_gem)
                    <span class="absolute start-2 top-2 rounded-full bg-accent-500 px-2 py-0.5 text-xs font-bold text-white">Hidden gem</span>
                @endif
            </div>
            <div class="flex flex-1 flex-col gap-2 p-4">
                <h3 class="font-semibold"><a href="{{ $place->url() }}" target="_blank" class="hover:text-primary-700">{{ $place->name }}</a></h3>
                <div class="flex flex-wrap gap-1">
                    @foreach ($place->categories as $category)
                        <span class="rounded-full bg-slate-100 px-2 py-0.5 text-xs text-slate-600">{{ $category->name }}</span>
                    @endforeach
                </div>
                <dl class="grid grid-cols-3 gap-2 text-xs text-slate-600">
                    <div><dt class="text-slate-400">Time</dt><dd>{{ $place->visitDuration() }}</dd></div>
                    <div><dt class="text-slate-400">Fee</dt><dd>{{ (float) $place->fee_foreign_adult > 0 ? \App\Support\Money::show($place->fee_foreign_adult) : 'Free' }}</dd></div>
                    <div><dt class="text-slate-400">Crowds</dt><dd>{{ $place->crowd_level ? ucfirst($place->crowd_level->value) : '—' }}</dd></div>
                </dl>
                @unless ($place->hasCoordinates())
                    <p class="text-xs text-accent-700">Map position not known yet: this place can't be routed.</p>
                @endunless
                @if ($on)
                    <div class="mt-auto flex items-center gap-2">
                        <span class="inline-flex flex-1 items-center gap-1 text-sm font-semibold text-primary-800"><x-icon name="check" class="size-4" /> In your trip</span>
                        <button type="button" wire:click="togglePlace({{ $place->id }})" aria-pressed="true" class="btn btn-secondary text-red-700 hover:bg-red-50" aria-label="Remove {{ $place->name }} from your trip">
                            <x-icon name="x" class="size-4" /> Remove
                        </button>
                    </div>
                @else
                    <button type="button" wire:click="togglePlace({{ $place->id }})" aria-pressed="false" class="btn btn-primary mt-auto">Add to trip</button>
                @endif
            </div>
        </article>
    @empty
        <p class="rounded-xl bg-slate-50 p-4 text-sm text-slate-600 sm:col-span-2">No published places here{{ $placeCategoryFilter ? ' for this interest' : '' }} yet.</p>
    @endforelse
</div>
@error('placeIds')
    <div class="mt-4 rounded-xl border border-red-200 bg-red-50 p-4 text-sm text-red-800" role="alert">
        <p>{{ $message }}</p>
        @if ($activeDistrict && ! ($pickedPerDistrict[$activeDistrict->id] ?? 0))
            <button type="button" wire:click="removeDistrict({{ $activeDistrict->id }})" class="mt-2 font-semibold underline">Remove {{ $activeDistrict->name }} from the trip</button>
        @endif
    </div>
@enderror

{{-- Guide the traveller through every chosen district --}}
@if ($nextDistrict)
    <div class="mt-6 flex flex-col items-start gap-2 rounded-2xl bg-slate-50 p-4 sm:flex-row sm:items-center sm:justify-between">
        <p class="text-sm text-slate-600">
            @if ($pickedPerDistrict[$activeDistrict->id] ?? 0) Done with {{ $activeDistrict->name }}? @else Nothing you like in {{ $activeDistrict->name }}? @endif
            You also chose {{ $nextDistrict->name }}.
        </p>
        <button type="button" wire:click="nextDistrict" class="btn btn-primary">Next district: {{ $nextDistrict->name }} <x-icon name="arrow-right" class="size-4" /></button>
    </div>
@endif
