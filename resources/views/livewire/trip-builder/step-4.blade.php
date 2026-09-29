<div class="flex flex-col gap-3 rounded-2xl bg-primary-50 p-4 sm:flex-row sm:items-center sm:justify-between">
    <p class="text-primary-900">
        <span class="text-2xl font-extrabold">{{ count($placeIds) }}</span> {{ Str::plural('place', count($placeIds)) }} chosen
        @if ($recommended)
            <span class="text-primary-800">≈ <strong>{{ $recommended }} {{ Str::plural('day', $recommended) }}</strong> recommended</span>
        @endif
    </p>
    <p class="text-xs text-primary-800">Based on visit times plus driving, at your travel style's pace.</p>
</div>

{{-- District tabs --}}
<div class="mt-5 flex gap-2 overflow-x-auto pb-1" role="tablist" aria-label="Districts">
    @foreach ($placeDistricts as $district)
        @php $active = $activeDistrict?->is($district); @endphp
        <button type="button" role="tab" aria-selected="{{ $active ? 'true' : 'false' }}" wire:click="showDistrict({{ $district->id }})" wire:key="tab-{{ $district->id }}"
                @class(['whitespace-nowrap rounded-full px-4 py-2 text-sm font-semibold transition', 'bg-slate-900 text-white' => $active, 'bg-white text-slate-700 ring-1 ring-slate-200 hover:bg-slate-50' => ! $active])>
            {{ $district->name }}
            @if ($pickedPerDistrict[$district->id] ?? 0)
                <span @class(['ms-1 rounded-full px-1.5 text-xs', 'bg-white/20' => $active, 'bg-primary-100 text-primary-800' => ! $active])>{{ $pickedPerDistrict[$district->id] }}</span>
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
                    <div><dt class="text-slate-400">Fee</dt><dd>{{ (float) $place->fee_foreign_adult > 0 ? '$'.number_format((float) $place->fee_foreign_adult) : 'Free' }}</dd></div>
                    <div><dt class="text-slate-400">Crowds</dt><dd>{{ $place->crowd_level ? ucfirst($place->crowd_level->value) : '—' }}</dd></div>
                </dl>
                @unless ($place->hasCoordinates())
                    <p class="text-xs text-accent-700">Map position not known yet: this place can't be routed.</p>
                @endunless
                <button type="button" wire:click="togglePlace({{ $place->id }})" aria-pressed="{{ $on ? 'true' : 'false' }}"
                        class="btn mt-auto {{ $on ? 'btn-secondary' : 'btn-primary' }}">
                    @if ($on)<x-icon name="check" class="size-4" /> Added @else Add to trip @endif
                </button>
            </div>
        </article>
    @empty
        <p class="rounded-xl bg-slate-50 p-4 text-sm text-slate-600 sm:col-span-2">No published places here{{ $placeCategoryFilter ? ' for this interest' : '' }} yet.</p>
    @endforelse
</div>
@error('placeIds')<p class="mt-3 text-sm text-red-600" role="alert">{{ $message }}</p>@enderror
