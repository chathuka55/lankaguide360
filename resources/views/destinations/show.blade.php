@php
    $cover = $photos->firstWhere('is_cover', true) ?? $photos->first();
    $lightboxPhotos = $photos->map(fn ($m) => [
        'src' => $m->url(1600),
        'alt' => $m->alt,
        'credit' => $m->credit() ?? '',
    ])->values();
    $fee = fn ($amount) => (float) $amount > 0 ? '$'.number_format((float) $amount, 2) : 'Free';
    $slotLabels = ['any' => 'Any time', 'sunrise' => 'Sunrise', 'morning' => 'Morning', 'afternoon' => 'Afternoon', 'sunset' => 'Sunset'];
@endphp

<x-app-layout :title="$place->name.', '.$place->district->name" :description="$place->short_description ?? $place->name.' in '.$place->district->name.', Sri Lanka.'" :image="$cover?->url(1600)" og-type="article">
    @push('head')
        @vite(['resources/js/map.js'])
        <script type="application/ld+json">{!! json_encode([
            '@context' => 'https://schema.org',
            '@graph' => [
                array_filter([
                    '@type' => 'TouristAttraction',
                    'name' => $place->name,
                    'description' => $place->short_description,
                    'url' => $place->url(),
                    'image' => $cover?->url(1600),
                    'geo' => $place->lat !== null ? ['@type' => 'GeoCoordinates', 'latitude' => (float) $place->lat, 'longitude' => (float) $place->lng] : null,
                    'address' => ['@type' => 'PostalAddress', 'addressRegion' => $place->district->name, 'addressCountry' => 'LK'],
                ]),
                [
                    '@type' => 'BreadcrumbList',
                    'itemListElement' => [
                        ['@type' => 'ListItem', 'position' => 1, 'name' => 'Destinations', 'item' => route('destinations.index')],
                        ['@type' => 'ListItem', 'position' => 2, 'name' => $place->district->name, 'item' => route('districts.show', $place->district)],
                        ['@type' => 'ListItem', 'position' => 3, 'name' => $place->name],
                    ],
                ],
            ],
        ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG) !!}</script>
    @endpush

    {{-- Breadcrumbs --}}
    <nav aria-label="Breadcrumb" class="border-b border-slate-200 bg-slate-50">
        <ol class="mx-auto flex max-w-7xl flex-wrap items-center gap-1 px-4 py-3 text-sm text-slate-600 sm:px-6 lg:px-8">
            <li><a href="{{ route('destinations.index') }}" class="hover:text-primary-700">Destinations</a></li>
            <li aria-hidden="true">/</li>
            <li><a href="{{ route('districts.show', $place->district) }}" class="hover:text-primary-700">{{ $place->district->name }}</a></li>
            <li aria-hidden="true">/</li>
            <li aria-current="page" class="font-medium text-slate-900">{{ $place->name }}</li>
        </ol>
    </nav>

    <div class="mx-auto max-w-7xl px-4 py-8 sm:px-6 lg:px-8">
        <div class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
            <div>
                <div class="flex flex-wrap gap-2">
                    @foreach ($place->categories as $category)
                        <a href="{{ route('destinations.index', ['category' => $category->slug]) }}" class="rounded-full bg-primary-50 px-3 py-1 text-xs font-semibold text-primary-800 hover:bg-primary-100">{{ $category->name }}</a>
                    @endforeach
                    @if ($place->is_hidden_gem)
                        <span class="rounded-full bg-accent-100 px-3 py-1 text-xs font-semibold text-accent-700">Hidden gem</span>
                    @endif
                </div>
                <h1 class="mt-3 text-3xl font-extrabold tracking-tight sm:text-4xl">{{ $place->name }}</h1>
                <p class="mt-1 flex items-center gap-1 text-slate-600"><x-icon name="map-pin" class="size-5" /> {{ $place->district->name }} District, {{ $place->district->province->name }}</p>
            </div>

            {{-- Add to my trip (JS toggle, plain form without JS) --}}
            <form method="POST" action="{{ route('plan.places.toggle', $place) }}" x-data="addToTrip({ url: @js(route('plan.places.toggle', $place)), added: @js($inTrip) })" @submit.prevent="toggle()">
                @csrf
                <button type="submit" :disabled="busy" class="btn px-5 py-3" :class="added ? 'btn-secondary' : 'btn-primary'">
                    <x-icon name="check" class="size-5" x-show="added" x-cloak />
                    <span x-text="added ? 'In your trip' : 'Add to my trip'">{{ $inTrip ? 'In your trip' : 'Add to my trip' }}</span>
                </button>
            </form>
        </div>

        {{-- Gallery --}}
        @if ($photos->isNotEmpty())
            <section class="mt-6" aria-label="Photos" x-data="lightbox(@js($lightboxPhotos))">
                <div class="grid gap-2 sm:grid-cols-4 sm:grid-rows-2">
                    @foreach ($photos->take(5) as $i => $photo)
                        <button type="button" @click="show({{ $i }})"
                                class="group relative overflow-hidden rounded-xl bg-slate-100 focus:outline-none focus-visible:ring-4 focus-visible:ring-primary-400 {{ $i === 0 ? 'aspect-[4/3] sm:col-span-2 sm:row-span-2 sm:aspect-auto' : 'hidden aspect-[4/3] sm:block' }}">
                            <span class="sr-only">Open photo {{ $i + 1 }} of {{ $photos->count() }}</span>
                            <x-media-img :media="$photo" :width="$i === 0 ? 1600 : 800" :eager="$i === 0" :sizes="$i === 0 ? '(min-width: 640px) 50vw, 100vw' : '25vw'" class="size-full object-cover transition duration-500 group-hover:scale-105" />
                            @if ($i === 4 && $photos->count() > 5)
                                <span class="absolute inset-0 grid place-items-center bg-slate-900/60 text-lg font-semibold text-white">+{{ $photos->count() - 5 }} photos</span>
                            @endif
                        </button>
                    @endforeach
                </div>
                @if ($cover)
                    <p class="mt-2 text-slate-500"><x-photo-credit :media="$cover" /></p>
                @endif

                {{-- Lightbox --}}
                <div x-show="open" x-cloak x-transition.opacity class="fixed inset-0 z-50 flex flex-col bg-slate-950/95 p-4" role="dialog" aria-modal="true" aria-label="Photo viewer"
                     @keydown.escape.window="open = false" @keydown.arrow-right.window="open && next()" @keydown.arrow-left.window="open && prev()">
                    <div class="flex justify-end">
                        <button type="button" x-ref="close" @click="open = false" class="rounded-full p-2 text-white hover:bg-white/10"><span class="sr-only">Close</span><x-icon name="x" class="size-7" /></button>
                    </div>
                    <div class="relative flex min-h-0 flex-1 items-center justify-center">
                        <button type="button" @click="prev()" class="absolute start-0 rounded-full bg-white/10 p-3 text-white hover:bg-white/20"><span class="sr-only">Previous photo</span><x-icon name="chevrons-left" class="size-6" /></button>
                        <img :src="current.src" :alt="current.alt" class="max-h-full max-w-full rounded-lg object-contain">
                        <button type="button" @click="next()" class="absolute end-0 rounded-full bg-white/10 p-3 text-white hover:bg-white/20"><span class="sr-only">Next photo</span><x-icon name="chevrons-left" class="size-6 rotate-180" /></button>
                    </div>
                    <p class="mt-3 text-center text-sm text-slate-300"><span x-text="(index + 1) + ' / ' + photos.length"></span> · <span x-text="current.credit"></span></p>
                </div>
            </section>
        @endif

        <div class="mt-10 grid gap-10 lg:grid-cols-3">
            <article class="lg:col-span-2">
                <h2 class="text-2xl font-bold">About {{ $place->name }}</h2>
                <div class="mt-4 space-y-4 text-base leading-7 text-slate-700">
                    @foreach (preg_split('/\n{2,}/', trim($place->description ?: $place->short_description ?: 'Details for this place are coming soon.')) as $paragraph)
                        <p>{{ $paragraph }}</p>
                    @endforeach
                </div>
                @if ($place->wikipedia_url)
                    <p class="mt-4 text-xs text-slate-500">
                        Text adapted from <a href="{{ $place->wikipedia_url }}" target="_blank" rel="noopener" class="underline">Wikipedia</a>,
                        available under <a href="https://creativecommons.org/licenses/by-sa/4.0/" target="_blank" rel="noopener license" class="underline">CC BY-SA 4.0</a>.
                    </p>
                @endif
            </article>

            <aside class="space-y-6">
                <section class="rounded-2xl bg-slate-50 p-5 ring-1 ring-slate-200" aria-labelledby="facts-heading">
                    <h2 id="facts-heading" class="font-semibold">Good to know</h2>
                    <dl class="mt-4 space-y-3 text-sm">
                        <div class="flex justify-between gap-4"><dt class="text-slate-500">Time needed</dt><dd class="font-medium">{{ $place->visitDuration() }}</dd></div>
                        <div class="flex justify-between gap-4"><dt class="text-slate-500">Entry (adult / child)</dt><dd class="font-medium">{{ $fee($place->fee_foreign_adult) }} / {{ $fee($place->fee_foreign_child) }}</dd></div>
                        <div class="flex justify-between gap-4"><dt class="text-slate-500">Opening hours</dt><dd class="font-medium">{{ $place->open_time && $place->close_time ? substr($place->open_time, 0, 5).'–'.substr($place->close_time, 0, 5) : 'Open access / check locally' }}</dd></div>
                        <div class="flex justify-between gap-4"><dt class="text-slate-500">Best time</dt><dd class="font-medium">{{ $slotLabels[$place->best_time_slot->value] }}@if ($place->best_months), {{ $place->best_months }}@endif</dd></div>
                        <div class="flex justify-between gap-4"><dt class="text-slate-500">Crowds</dt><dd class="font-medium">{{ ucfirst($place->crowd_level->value) }}</dd></div>
                    </dl>
                    <p class="mt-4 text-xs text-slate-500">Fees are for foreign visitors and may change; your agent confirms them.</p>
                </section>

                @if ($place->hasCoordinates())
                    <section aria-label="Location map">
                        <div x-data="pinMap({ lat: {{ (float) $place->lat }}, lng: {{ (float) $place->lng }}, label: @js($place->name) })" class="h-64 overflow-hidden rounded-2xl ring-1 ring-slate-200"></div>
                        <a href="https://www.openstreetmap.org/?mlat={{ $place->lat }}&mlon={{ $place->lng }}#map=14/{{ $place->lat }}/{{ $place->lng }}" target="_blank" rel="noopener" class="mt-2 inline-block text-xs text-primary-700 hover:underline">Open in OpenStreetMap</a>
                    </section>
                @endif

                <a href="{{ route('plan') }}" class="btn btn-secondary w-full">Plan a trip with {{ $place->name }}</a>
            </aside>
        </div>

        @if ($nearbyPlaces->isNotEmpty())
            <section class="mt-16" aria-labelledby="nearby-heading">
                <x-section-heading id="nearby-heading" title="Nearby places" subtitle="Within 30 km — easy to combine on the same day." />
                <div class="grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
                    @foreach ($nearbyPlaces as $nearby)
                        <div class="relative">
                            <x-place-card :place="$nearby" />
                            <span class="pointer-events-none absolute end-3 top-3 rounded-full bg-white/95 px-2 py-0.5 text-xs font-semibold text-slate-700 shadow">{{ number_format($nearby->distance_km, 1) }} km</span>
                        </div>
                    @endforeach
                </div>
            </section>
        @endif

        @if ($nearbyHotels->isNotEmpty())
            <section class="mt-16" aria-labelledby="hotels-heading">
                <x-section-heading id="hotels-heading" title="Where to stay nearby" subtitle="Hotels within 20 km, by travel style." />
                <div class="grid gap-6 md:grid-cols-3">
                    @foreach ($nearbyHotels as $tier => $hotels)
                        <div class="rounded-2xl bg-white p-5 ring-1 ring-slate-200">
                            <h3 class="font-semibold">{{ ucfirst($tier) }}</h3>
                            <ul class="mt-3 space-y-3 text-sm">
                                @foreach ($hotels as $hotel)
                                    <li>
                                        <p class="font-medium text-slate-900">{{ $hotel->name }}@if ($hotel->star_rating) <span class="text-accent-600">{{ str_repeat('★', $hotel->star_rating) }}</span>@endif</p>
                                        <p class="text-slate-500">{{ $hotel->town }} · {{ number_format($hotel->distance_km, 1) }} km</p>
                                    </li>
                                @endforeach
                            </ul>
                        </div>
                    @endforeach
                </div>
                <p class="mt-3 text-xs text-slate-500">Hotel data © OpenStreetMap contributors. The Trip Builder suggests hotels with prices for your dates.</p>
            </section>
        @endif
    </div>
</x-app-layout>
