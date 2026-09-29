@php
    $activeCategory = $categories->firstWhere('slug', $filters['category']);
    $query = fn (array $overrides) => array_filter([...request()->except('page'), ...$overrides], fn ($v) => filled($v));
@endphp

<x-app-layout :title="$activeCategory ? $activeCategory->name.' in Sri Lanka' : 'Destinations'"
              description="Browse Sri Lanka's beaches, ancient cities, hill country, wildlife parks and hidden gems by category, district and province.">
    @if ($filters['view'] === 'map')
        @push('head') @vite(['resources/js/map.js']) @endpush
    @endif

    <x-page-header eyebrow="Explore" :title="$activeCategory ? $activeCategory->name : 'Destinations'" subtitle="Beaches, ancient cities, tea country, wildlife and hidden gems across all 25 districts." />

    <div class="mx-auto max-w-7xl px-4 py-10 sm:px-6 lg:px-8">
        {{-- Filters (query-string state) --}}
        <form method="GET" action="{{ route('destinations.index') }}" class="grid gap-3 rounded-2xl bg-white p-4 shadow-sm ring-1 ring-slate-200 sm:grid-cols-2 lg:grid-cols-6" role="search">
            <input type="hidden" name="view" value="{{ $filters['view'] }}">
            <div class="sm:col-span-2">
                <label for="d-q" class="form-label">Search</label>
                <input id="d-q" type="search" name="q" value="{{ $filters['q'] }}" placeholder="e.g. Sigiriya, waterfall" class="form-control">
            </div>
            <div>
                <label for="d-category" class="form-label">Category</label>
                <select id="d-category" name="category" class="form-control">
                    <option value="">All</option>
                    @foreach ($categories as $category)
                        <option value="{{ $category->slug }}" @selected($filters['category'] === $category->slug)>{{ $category->name }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label for="d-province" class="form-label">Province</label>
                <select id="d-province" name="province" class="form-control">
                    <option value="">All</option>
                    @foreach ($provinces as $province)
                        <option value="{{ $province->slug }}" @selected($filters['province'] === $province->slug)>{{ Str::before($province->name, ' Province') }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label for="d-district" class="form-label">District</label>
                <select id="d-district" name="district" class="form-control">
                    <option value="">All</option>
                    @foreach ($provinces as $province)
                        <optgroup label="{{ $province->name }}">
                            @foreach ($province->districts as $district)
                                <option value="{{ $district->slug }}" @selected($filters['district'] === $district->slug)>{{ $district->name }}</option>
                            @endforeach
                        </optgroup>
                    @endforeach
                </select>
            </div>
            <div class="flex flex-col justify-end gap-2">
                <label class="inline-flex items-center gap-2 text-sm"><input type="checkbox" name="gems" value="1" @checked($filters['gems']) class="form-check"> Hidden gems only</label>
                <button type="submit" class="btn btn-primary">Show places</button>
            </div>
        </form>

        {{-- Result bar + view toggle --}}
        <div class="mt-6 flex flex-wrap items-center justify-between gap-3">
            <p class="text-sm text-slate-600" role="status">
                {{ number_format($total) }} {{ Str::plural('place', $total) }}
                @if (collect($filters)->except('view')->filter()->isNotEmpty())
                    · <a href="{{ route('destinations.index', ['view' => $filters['view']]) }}" class="font-medium text-primary-700 hover:underline">Clear filters</a>
                @endif
            </p>
            <div class="inline-flex rounded-lg bg-slate-100 p-1 text-sm font-medium" role="group" aria-label="View">
                <a href="{{ route('destinations.index', $query(['view' => 'grid'])) }}" @if ($filters['view'] === 'grid') aria-current="page" @endif
                   class="rounded-md px-4 py-1.5 {{ $filters['view'] === 'grid' ? 'bg-white text-slate-900 shadow' : 'text-slate-600 hover:text-slate-900' }}">Grid</a>
                <a href="{{ route('destinations.index', $query(['view' => 'map'])) }}" @if ($filters['view'] === 'map') aria-current="page" @endif
                   class="rounded-md px-4 py-1.5 {{ $filters['view'] === 'map' ? 'bg-white text-slate-900 shadow' : 'text-slate-600 hover:text-slate-900' }}">Map</a>
            </div>
        </div>

        @if ($filters['view'] === 'map')
            <div x-data="placesMap(@js($mapPlaces))" class="mt-4 h-[70vh] min-h-96 overflow-hidden rounded-2xl ring-1 ring-slate-200" role="region" aria-label="Map of places"></div>
            <p class="mt-2 text-xs text-slate-500">Map data © OpenStreetMap contributors. Places without map coordinates are listed in the grid view.</p>
        @elseif ($places->isEmpty())
            <div class="mt-10 rounded-2xl bg-slate-50 p-10 text-center">
                <h2 class="text-lg font-semibold">No places match these filters</h2>
                <p class="mt-1 text-slate-600">Try another category or district, or <a href="{{ route('destinations.index') }}" class="text-primary-700 underline">see all destinations</a>.</p>
            </div>
        @else
            <div class="mt-4 grid gap-6 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">
                @foreach ($places as $place)
                    <x-place-card :place="$place" />
                @endforeach
            </div>
            <div class="mt-8">{{ $places->links() }}</div>
        @endif
    </div>
</x-app-layout>
