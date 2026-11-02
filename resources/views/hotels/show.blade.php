@php
    $cover = $photos->first();
    $lightboxPhotos = $photos->map(fn ($m) => ['src' => $m->url(1600), 'alt' => $m->alt ?: $hotel->name, 'credit' => $m->credit() ?? ''])->values();
    $amenities = collect($hotel->amenities ?? [])->map(fn ($a) => App\Models\Hotel::AMENITY_LABELS[$a] ?? Str::headline($a));
    $jsonLd = array_filter([
        '@context' => 'https://schema.org',
        '@type' => 'Hotel',
        'name' => $hotel->name,
        'description' => $hotel->summary(),
        'url' => $hotel->url(),
        'image' => $cover?->url(1600),
        'telephone' => $hotel->phone,
        'starRating' => $hotel->star_rating ? ['@type' => 'Rating', 'ratingValue' => $hotel->star_rating] : null,
        'address' => ['@type' => 'PostalAddress', 'streetAddress' => $hotel->address, 'addressLocality' => $hotel->town, 'addressRegion' => $hotel->district?->name, 'addressCountry' => 'LK'],
        'geo' => $hotel->lat !== null ? ['@type' => 'GeoCoordinates', 'latitude' => (float) $hotel->lat, 'longitude' => (float) $hotel->lng] : null,
    ]);
@endphp

<x-app-layout :title="$hotel->name.', '.$hotel->town" :description="Str::limit($hotel->summary(), 155)" :image="$cover?->url(1600)">
    @push('head')
        @vite(['resources/js/map.js'])
        @unless ($hotel->is_demo)<script type="application/ld+json">{!! json_encode($jsonLd, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG) !!}</script>@endunless
        @if ($hotel->is_demo)<meta name="robots" content="noindex">@endif
    @endpush

    <nav aria-label="Breadcrumb" class="border-b border-slate-200 bg-slate-50">
        <ol class="mx-auto flex max-w-7xl flex-wrap items-center gap-1 px-4 py-3 text-sm text-slate-600 sm:px-6 lg:px-8">
            <li><a href="{{ route('destinations.index') }}" class="hover:text-primary-700">Destinations</a></li>
            @if ($hotel->district)
                <li aria-hidden="true">/</li>
                <li><a href="{{ route('districts.show', $hotel->district) }}" class="hover:text-primary-700">{{ $hotel->district->name }}</a></li>
            @endif
            <li aria-hidden="true">/</li>
            <li aria-current="page" class="font-medium text-slate-900">{{ $hotel->name }}</li>
        </ol>
    </nav>

    <div class="mx-auto max-w-7xl px-4 py-8 sm:px-6 lg:px-8">
        <div class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
            <div>
                <div class="flex flex-wrap items-center gap-2">
                    <span class="rounded-full bg-primary-50 px-3 py-1 text-xs font-semibold text-primary-800">{{ $hotel->tier->label() }}</span>
                    <span class="rounded-full bg-slate-100 px-3 py-1 text-xs font-semibold text-slate-700">{{ Str::headline($hotel->type ?: 'hotel') }}</span>
                    <x-demo-badge :hotel="$hotel" />
                </div>
                <h1 class="mt-3 text-3xl font-extrabold tracking-tight sm:text-4xl">{{ $hotel->name }}</h1>
                <p class="mt-1 flex flex-wrap items-center gap-x-3 gap-y-1 text-slate-600">
                    <span class="inline-flex items-center gap-1"><x-icon name="map-pin" class="size-5" /> {{ $hotel->town }}@if ($hotel->district), {{ $hotel->district->name }} District @endif</span>
                    @if ($hotel->star_rating)
                        <span class="text-accent-500" aria-label="{{ $hotel->star_rating }} stars">{{ str_repeat('★', $hotel->star_rating) }}<span class="text-slate-300">{{ str_repeat('★', max(0, 5 - $hotel->star_rating)) }}</span></span>
                    @endif
                </p>
            </div>
            <div class="text-start sm:text-end">
                @if ($fromRate)
                    <p class="text-sm text-slate-500">Rooms from</p>
                    <p class="text-3xl font-extrabold">@money($fromRate)</p>
                    <p class="text-xs text-slate-500">per room per night @approxUsd($fromRate)</p>
                @else
                    <p class="text-sm text-slate-500">Price on request — your agent confirms it.</p>
                @endif
            </div>
        </div>

        {{-- Gallery --}}
        <section class="mt-6" aria-label="Photos" x-data="lightbox(@js($lightboxPhotos))">
            @if ($photos->isNotEmpty())
                <div class="grid gap-2 sm:grid-cols-4 sm:grid-rows-2">
                    @foreach ($photos->take(5) as $i => $photo)
                        <button type="button" @click="show({{ $i }})" class="group relative overflow-hidden rounded-xl bg-slate-100 focus:outline-none focus-visible:ring-4 focus-visible:ring-primary-400 {{ $i === 0 ? 'aspect-[4/3] sm:col-span-2 sm:row-span-2 sm:aspect-auto' : 'hidden aspect-[4/3] sm:block' }}">
                            <span class="sr-only">Open photo {{ $i + 1 }} of {{ $photos->count() }}</span>
                            <x-media-img :media="$photo" :width="$i === 0 ? 1600 : 800" :eager="$i === 0" :sizes="$i === 0 ? '(min-width: 640px) 50vw, 100vw' : '25vw'" class="size-full object-cover transition duration-500 group-hover:scale-105" />
                        </button>
                    @endforeach
                </div>
            @else
                <div class="grid aspect-[21/9] place-items-center rounded-2xl bg-gradient-to-br from-primary-50 via-white to-accent-50 text-center text-slate-500">
                    <div><x-icon name="building" class="mx-auto size-12 text-primary-300" /><p class="mt-2 text-sm">Photos of this hotel are coming soon.</p></div>
                </div>
            @endif
        </section>

        <div class="mt-8 grid gap-8 lg:grid-cols-3">
            <div class="space-y-8 lg:col-span-2">
                <section aria-labelledby="about-heading">
                    <h2 id="about-heading" class="text-2xl font-bold">About this {{ str_replace('_', ' ', $hotel->type ?: 'hotel') }}</h2>
                    <p class="mt-3 leading-7 text-slate-700">{{ $hotel->summary() }}</p>
                    @if ($hotel->is_demo)
                        <p class="mt-3 rounded-lg bg-slate-100 px-3 py-2 text-sm text-slate-600">This is a sample listing used to demonstrate the Trip Builder. Your agent books a real hotel of the same standard.</p>
                    @endif
                </section>

                @if ($amenities->isNotEmpty())
                    <section aria-labelledby="amenities-heading">
                        <h2 id="amenities-heading" class="text-xl font-bold">Facilities</h2>
                        <ul class="mt-3 grid gap-2 sm:grid-cols-2" role="list">
                            @foreach ($amenities as $amenity)
                                <li class="flex items-center gap-2 text-slate-700"><x-icon name="check" class="size-5 text-primary-700" /> {{ $amenity }}</li>
                            @endforeach
                        </ul>
                    </section>
                @endif

                <section aria-labelledby="rooms-heading">
                    <h2 id="rooms-heading" class="text-xl font-bold">Rooms and prices</h2>
                    @if ($rates->isEmpty())
                        <p class="mt-3 text-slate-600">We don't have room prices for this hotel yet. Add it to a trip plan and your agent will quote it.</p>
                    @else
                        <div class="mt-3 overflow-x-auto rounded-2xl border border-slate-200">
                            <table class="w-full text-sm">
                                <thead class="bg-slate-50 text-start text-xs uppercase tracking-wide text-slate-500">
                                    <tr><th class="px-4 py-2 text-start">Room</th><th class="px-4 py-2 text-start">Meals</th><th class="px-4 py-2 text-start">Sleeps</th><th class="px-4 py-2 text-end">Per night</th></tr>
                                </thead>
                                <tbody class="divide-y divide-slate-100">
                                    @foreach ($rates as $room => $roomRates)
                                        @foreach ($roomRates as $rate)
                                            <tr>
                                                <td class="px-4 py-2 font-medium">@if ($loop->first){{ $room }}@endif</td>
                                                <td class="px-4 py-2 text-slate-600">{{ $rate->meal_plan->label() }}</td>
                                                <td class="px-4 py-2 text-slate-600">{{ $rate->max_occupancy }}</td>
                                                <td class="px-4 py-2 text-end font-semibold">@money($rate->price_per_night)@if ($rate->is_estimate)<span class="block text-xs font-normal text-slate-400">estimate</span>@endif</td>
                                            </tr>
                                        @endforeach
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                        <p class="mt-2 text-xs text-slate-500">Prices per room per night. Your agent confirms availability and the final price for your dates.</p>
                    @endif
                </section>

                @if ($nearbyPlaces->isNotEmpty())
                    <section aria-labelledby="nearby-heading">
                        <h2 id="nearby-heading" class="text-xl font-bold">Places to visit nearby</h2>
                        <ul class="mt-3 grid gap-3 sm:grid-cols-2" role="list">
                            @foreach ($nearbyPlaces as $place)
                                <li>
                                    <a href="{{ $place->url() }}" class="flex items-center gap-3 rounded-xl border border-slate-200 bg-white p-3 hover:border-primary-300">
                                        <span class="block size-14 shrink-0 overflow-hidden rounded-lg bg-primary-50">
                                            @if ($place->cover)<x-media-img :media="$place->cover" :width="400" sizes="56px" alt="" class="size-full object-cover" />@endif
                                        </span>
                                        <span><span class="block font-medium text-slate-900">{{ $place->name }}</span><span class="text-xs text-slate-500">{{ number_format($place->distance_km, 1) }} km away</span></span>
                                    </a>
                                </li>
                            @endforeach
                        </ul>
                    </section>
                @endif
            </div>

            <aside class="space-y-6">
                @if ($hotel->lat !== null)
                    <section aria-labelledby="location-heading" class="rounded-2xl border border-slate-200 bg-white p-4">
                        <h2 id="location-heading" class="font-semibold">Location</h2>
                        <div x-data="pinMap({ lat: {{ (float) $hotel->lat }}, lng: {{ (float) $hotel->lng }}, label: @js($hotel->name), zoom: 14 })" class="mt-3 h-56 overflow-hidden rounded-xl ring-1 ring-slate-200"></div>
                        <a href="{{ $hotel->googleMapsUrl() }}" target="_blank" rel="noopener" class="btn btn-secondary mt-3 w-full">Open in Google Maps <x-icon name="external" class="size-4" /></a>
                    </section>
                @endif

                <section aria-labelledby="contact-heading" class="rounded-2xl border border-slate-200 bg-white p-4 text-sm">
                    <h2 id="contact-heading" class="font-semibold">Contact</h2>
                    <dl class="mt-3 space-y-2">
                        @if ($hotel->address)<div><dt class="text-slate-500">Address</dt><dd>{{ $hotel->address }}</dd></div>@endif
                        @if ($hotel->phone)<div><dt class="text-slate-500">Phone</dt><dd><a href="tel:{{ preg_replace('/[^\d+]/', '', $hotel->phone) }}" class="text-primary-700 hover:underline">{{ $hotel->phone }}</a></dd></div>@endif
                        @if ($hotel->website)<div><dt class="text-slate-500">Website</dt><dd><a href="{{ $hotel->website }}" target="_blank" rel="noopener nofollow" class="break-all text-primary-700 hover:underline">{{ preg_replace('#^https?://(www\.)?#', '', rtrim($hotel->website, '/')) }}</a></dd></div>@endif
                        @if (! $hotel->address && ! $hotel->phone && ! $hotel->website)<p class="text-slate-500">Book through LankaGuide360 and your agent arranges everything.</p>@endif
                    </dl>
                    <a href="{{ route('plan') }}" class="btn btn-primary mt-4 w-full">Plan a trip with a stay here</a>
                </section>

                @unless ($hotel->is_demo)
                    <p class="text-xs text-slate-500">Hotel data © <a href="https://www.openstreetmap.org/copyright" target="_blank" rel="noopener" class="underline">OpenStreetMap contributors</a>.</p>
                @endunless
            </aside>
        </div>
    </div>
</x-app-layout>
