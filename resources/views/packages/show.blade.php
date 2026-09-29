@php
    $cover = $package->cover;
    $lines = fn (?string $text) => collect(preg_split('/\r?\n/', (string) $text))->map(fn ($l) => trim($l, " \t-•"))->filter();
    $jsonLd = [
        '@context' => 'https://schema.org',
        '@type' => 'TouristTrip',
        'name' => $package->name,
        'description' => $package->summary,
        'url' => route('packages.show', $package),
        'itinerary' => [
            '@type' => 'ItemList',
            'itemListElement' => $days->values()->map(fn ($day, $i) => ['@type' => 'ListItem', 'position' => $i + 1, 'name' => 'Day '.$day->day_number.': '.$day->title])->all(),
        ],
    ];
    if ($package->from_price) {
        $jsonLd['offers'] = ['@type' => 'Offer', 'price' => (string) $package->from_price, 'priceCurrency' => 'USD'];
    }
@endphp

<x-app-layout :title="$package->name" :description="$package->summary ?? $package->name.' — a ready-made Sri Lanka trip.'" :image="$cover?->url(1600)">
    @push('head')
        @vite(['resources/js/map.js'])
        <script type="application/ld+json">{!! json_encode($jsonLd, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG) !!}</script>
    @endpush

    <nav aria-label="Breadcrumb" class="border-b border-slate-200 bg-slate-50">
        <ol class="mx-auto flex max-w-7xl flex-wrap items-center gap-1 px-4 py-3 text-sm text-slate-600 sm:px-6 lg:px-8">
            <li><a href="{{ route('home') }}" class="hover:text-primary-700">Home</a></li>
            <li aria-hidden="true">/</li>
            <li><a href="{{ route('packages.index') }}" class="hover:text-primary-700">Packages</a></li>
            <li aria-hidden="true">/</li>
            <li aria-current="page" class="font-medium text-slate-900">{{ $package->name }}</li>
        </ol>
    </nav>

    <div class="mx-auto max-w-7xl px-4 py-8 sm:px-6 lg:px-8">
        <div class="grid gap-8 lg:grid-cols-3">
            <div class="space-y-8 lg:col-span-2">
                <div>
                    @if ($package->tier)
                        <span class="rounded-full bg-primary-50 px-3 py-1 text-xs font-semibold text-primary-800">{{ $package->tier->label() }}</span>
                    @endif
                    <h1 class="mt-3 text-3xl font-extrabold tracking-tight sm:text-4xl">{{ $package->name }}</h1>
                    @if ($package->summary)
                        <p class="mt-3 text-lg text-slate-600">{{ $package->summary }}</p>
                    @endif
                </div>

                @if ($cover)
                    <figure class="overflow-hidden rounded-2xl">
                        <x-media-img :media="$cover" :width="1600" :eager="true" sizes="(min-width: 1024px) 66vw, 100vw" :alt="$package->name" class="aspect-[16/9] w-full object-cover" />
                        <x-photo-credit :media="$cover" />
                    </figure>
                @endif

                @if (collect($mapDays)->flatMap(fn ($d) => $d['stops'])->isNotEmpty())
                    <div class="h-80 overflow-hidden rounded-2xl border border-slate-200 bg-slate-100" x-data="tripMap({ days: @js($mapDays) })" role="img" aria-label="Map of the package route"></div>
                @endif

                <section aria-labelledby="days-heading">
                    <h2 id="days-heading" class="text-xl font-bold">Day by day</h2>
                    <ol class="mt-4 space-y-3">
                        @foreach ($days as $day)
                            <li class="rounded-2xl border border-slate-200 bg-white p-5">
                                <h3 class="font-semibold"><span class="text-primary-700">Day {{ $day->day_number }}</span> · {{ $day->title }}</h3>
                                @php $stops = $day->stops->filter(fn ($s) => $s->place?->isPublished()); @endphp
                                @if ($stops->isNotEmpty())
                                    <ul class="mt-2 flex flex-wrap gap-2 text-sm">
                                        @foreach ($stops as $stop)
                                            <li><a href="{{ $stop->place->url() }}" class="inline-flex items-center gap-1 rounded-full bg-slate-100 px-3 py-1 text-slate-700 hover:bg-primary-50 hover:text-primary-800"><x-icon name="map-pin" class="size-3.5" /> {{ $stop->place->name }}</a></li>
                                        @endforeach
                                    </ul>
                                @endif
                                @if ($day->overnight_town)
                                    <p class="mt-2 text-sm text-slate-500">Overnight in {{ $day->overnight_town }}</p>
                                @endif
                            </li>
                        @endforeach
                    </ol>
                </section>
            </div>

            <aside class="lg:sticky lg:top-24 lg:self-start">
                <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
                    <p class="text-sm text-slate-500">{{ $package->days }} days @if ($package->from_price) · from @endif</p>
                    @if ($package->from_price)
                        <p class="text-3xl font-extrabold">${{ number_format((float) $package->from_price) }} <span class="text-base font-normal text-slate-500">per person</span></p>
                    @endif
                    <p class="mt-2 text-xs text-slate-500">Final price depends on dates, group size and hotels. An agent confirms it before you pay anything.</p>

                    <div class="mt-5 space-y-2">
                        <form method="POST" action="{{ route('packages.customize', $package) }}">
                            @csrf
                            <input type="hidden" name="mode" value="book">
                            <button type="submit" class="btn btn-primary w-full py-3">Book Now</button>
                        </form>
                        <form method="POST" action="{{ route('packages.customize', $package) }}">
                            @csrf
                            <input type="hidden" name="mode" value="customize">
                            <button type="submit" class="btn btn-secondary w-full py-3">Customize this trip</button>
                        </form>
                    </div>

                    @foreach (['Included' => $package->inclusions, 'Not included' => $package->exclusions] as $heading => $text)
                        @if ($lines($text)->isNotEmpty())
                            <h2 class="mt-6 text-sm font-semibold">{{ $heading }}</h2>
                            <ul class="mt-2 space-y-1 text-sm text-slate-600">
                                @foreach ($lines($text) as $line)
                                    <li class="flex gap-2"><span aria-hidden="true" class="{{ $heading === 'Included' ? 'text-primary-700' : 'text-slate-400' }}">{{ $heading === 'Included' ? '✓' : '–' }}</span> {{ $line }}</li>
                                @endforeach
                            </ul>
                        @endif
                    @endforeach
                </div>
            </aside>
        </div>
    </div>
</x-app-layout>
