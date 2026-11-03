<x-app-layout :title="$district->name.' District'" :description="Str::limit($district->description ?: 'Places to visit and hotels in '.$district->name.' District, '.$district->province->name.', Sri Lanka.', 160)">
    @push('head') @vite(['resources/js/map.js']) @endpush

    <x-page-header :eyebrow="$district->province->name" :title="$district->name.' District'"
                   :subtitle="$district->categories->pluck('name')->implode(' · ')" />

    <div class="mx-auto max-w-7xl px-4 py-10 sm:px-6 lg:px-8">
        <div class="grid gap-8 lg:grid-cols-5">
            <div class="lg:col-span-2">
                <h2 class="text-xl font-bold">About the district</h2>
                <p class="mt-3 leading-7 text-slate-700">{{ $district->description ?: $district->name.' is one of Sri Lanka\'s 25 districts, in the '.$district->province->name.'. Explore its places below and add them to your trip.' }}</p>
                <div class="mt-6 flex flex-wrap gap-3">
                    <a href="{{ route('destinations.index', ['district' => $district->slug]) }}" class="btn btn-secondary">All {{ $places->count() }} places</a>
                    <a href="{{ route('plan') }}" class="btn btn-primary">Plan a trip</a>
                </div>
            </div>
            <div class="lg:col-span-3">
                <div x-data="districtMap({ slug: @js($district->slug), places: @js($mapPlaces), geojsonUrl: @js(asset('geo/lk-districts.geojson')) })"
                     class="h-80 overflow-hidden rounded-2xl ring-1 ring-slate-200" role="region" aria-label="Map of {{ $district->name }} District"></div>
                <p class="mt-2 text-xs text-slate-500">Map data © OpenStreetMap contributors. District boundary: geoBoundaries (ODbL).</p>
            </div>
        </div>

        @forelse ($placesByCategory as $group)
            <section class="mt-14" aria-labelledby="cat-{{ $group['category']->slug }}">
                <x-section-heading :id="'cat-'.$group['category']->slug" :title="$group['category']->name" :link="route('destinations.index', ['district' => $district->slug, 'category' => $group['category']->slug])" />
                <div class="grid gap-6 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">
                    @foreach ($group['places'] as $place)
                        <x-place-card :place="$place->setRelation('district', $district)" />
                    @endforeach
                </div>
            </section>
        @empty
            <p class="mt-14 rounded-2xl bg-slate-50 p-8 text-center text-slate-600">No places have been published for this district yet.</p>
        @endforelse

        @if ($hotels->isNotEmpty())
            <section class="mt-14" aria-labelledby="district-hotels">
                <x-section-heading id="district-hotels" title="Places to stay" />
                <ul class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                    @foreach ($hotels as $hotel)
                        @php($photo = $hotel->cover ?? $hotel->sampleCover())
                        <li class="group relative overflow-hidden rounded-xl bg-white ring-1 ring-slate-200 transition hover:shadow-lg">
                            <div class="relative aspect-video bg-primary-50">
                                @if ($photo)
                                    <x-media-img :media="$photo" :width="400" sizes="(min-width: 1024px) 30vw, (min-width: 640px) 45vw, 100vw" :alt="$hotel->cover ? $hotel->name : 'Sample photo: '.$photo->alt" class="size-full object-cover transition duration-500 group-hover:scale-105" />
                                    @unless ($hotel->cover)
                                        <span class="absolute start-2 top-2 rounded-full bg-slate-900/75 px-2 py-0.5 text-[10px] font-semibold text-white" title="{{ $photo->credit() }}">Sample photo</span>
                                    @endunless
                                @endif
                            </div>
                            <div class="p-4">
                                <p class="font-semibold"><a href="{{ $hotel->url() }}" class="after:absolute after:inset-0 hover:text-primary-700">{{ $hotel->name }}</a> <x-demo-badge :hotel="$hotel" />@if ($hotel->star_rating) <span class="text-accent-600">{{ str_repeat('★', $hotel->star_rating) }}</span>@endif</p>
                                <p class="text-sm text-slate-500">{{ $hotel->town }} · {{ $hotel->tier->label() }}</p>
                            </div>
                        </li>
                    @endforeach
                </ul>
            </section>
        @endif
    </div>
</x-app-layout>
