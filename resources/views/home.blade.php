@php
    $taglines = [
        'beach' => 'Golden bays, surf breaks and whale watching along 1,300 km of coast.',
        'temple' => 'Ancient capitals, rock fortresses and sacred temples.',
        'tea' => 'Misty tea estates, cool hill towns and the famous train ride.',
        'elephant' => 'Elephants, leopards and birds in wild national parks.',
        'mountain' => 'Sunrise peaks, waterfalls and jungle trails.',
        'gem' => 'Quiet corners most visitors never find.',
    ];
    $interestOptions = $siteCategories->map(fn ($c) => ['slug' => $c->slug, 'name' => $c->name])->values();
    $firstHero = $heroPlaces->first();
@endphp

<x-app-layout :image="$firstHero?->cover?->url(1600)" description="Build your own day-by-day Sri Lanka trip in minutes: beaches, ancient cities, tea country and wildlife, with hotels, transport and a full price, confirmed by a local travel agent.">
    @push('head') @vite(['resources/js/home.js']) @endpush

    <div class="bg-sand-50">
        {{-- ================================================================ Hero --}}
        <section class="px-2 pt-2 sm:px-4 sm:pt-4" aria-labelledby="hero-heading" x-data="heroSlider({{ $heroPlaces->count() }})">
            <div class="relative isolate overflow-hidden rounded-[1.75rem] bg-primary-900 sm:rounded-[2.5rem]" data-hero>
                {{-- Background slideshow (Swiper, fade) --}}
                @if ($heroPlaces->isNotEmpty())
                    <div class="swiper absolute! inset-0 -z-20!" x-ref="slides">
                        <div class="swiper-wrapper">
                            @foreach ($heroPlaces as $i => $place)
                                <div class="swiper-slide overflow-hidden">
                                    <x-media-img :media="$place->cover" :width="1600" :eager="$i === 0" sizes="100vw" alt="" class="size-full scale-110 object-cover saturate-[1.2] contrast-[1.05]" data-hero-image />
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endif
                {{-- Cream only behind the text; the photo keeps its colour on the right. --}}
                <div class="absolute inset-0 -z-10 bg-gradient-to-b from-sand-50/95 via-sand-50/65 via-45% to-transparent to-80% lg:bg-gradient-to-r lg:from-sand-50/95 lg:via-sand-50/50 lg:via-30% lg:to-transparent lg:to-55%"></div>
                <div class="absolute inset-0 -z-10 bg-gradient-to-br from-transparent via-transparent to-primary-900/25"></div>
                <div class="absolute inset-x-0 bottom-0 -z-10 h-1/2 bg-gradient-to-t from-primary-950/60 to-transparent"></div>

                <div class="grid gap-10 px-5 pt-10 pb-8 sm:px-10 lg:min-h-[min(78vh,46rem)] lg:grid-cols-12 lg:pt-16" data-hero-content>
                    {{-- Headline + quick planner --}}
                    <div class="lg:col-span-7 xl:col-span-6">
                        <p class="inline-flex items-center gap-2 rounded-full bg-white/80 px-3 py-1.5 text-xs font-semibold text-primary-800 ring-1 ring-primary-900/10 backdrop-blur">
                            <span class="relative flex size-2"><span class="absolute inline-flex size-full animate-ping rounded-full bg-accent-400 opacity-75 motion-reduce:hidden"></span><span class="relative inline-flex size-2 rounded-full bg-accent-500"></span></span>
                            Sri Lanka trip planner · 25 districts
                        </p>
                        <h1 id="hero-heading" class="mt-6 text-[3.4rem] leading-[0.92] font-extrabold tracking-tight text-primary-950 sm:text-7xl xl:text-8xl">
                            <span class="block" data-hero-line>Plan less.</span>
                            <span class="block font-display font-normal italic text-primary-800" data-hero-line>Explore more.</span>
                        </h1>
                        <p class="mt-6 max-w-md text-lg leading-relaxed text-slate-700" data-hero-line>
                            Build a day-by-day Sri Lanka trip in minutes, with hotels, transport and a full price. A local agent checks every detail.
                        </p>

                        {{-- Quick planner: interest dropdown → Trip Builder --}}
                        {{-- z-20: the entrance animation gives each hero line its own layer; keep the open list above the links below. --}}
                        <form method="GET" action="{{ route('plan') }}" class="relative z-20 mt-8 flex max-w-lg flex-col gap-2 rounded-3xl bg-white p-2 shadow-xl shadow-primary-950/10 ring-1 ring-primary-950/5 sm:flex-row sm:items-center sm:rounded-full sm:p-1.5"
                              x-data="interestPicker(@js($interestOptions))" @keydown.escape.stop="close()" data-hero-line>
                            <input type="hidden" name="category" :value="selected?.slug" :disabled="! selected">
                            <div class="relative flex-1">
                                <button type="button" x-ref="button" @click="toggle()" @keydown.arrow-down.prevent="openList()" aria-haspopup="listbox" :aria-expanded="open.toString()" aria-controls="interest-list"
                                        class="flex w-full items-center gap-3 rounded-full px-2 py-1.5 text-start hover:bg-sand-100 focus:outline-none focus-visible:ring-2 focus-visible:ring-primary-500">
                                    <span class="grid size-10 shrink-0 place-items-center rounded-full bg-primary-50 text-primary-700">
                                        <x-icon name="sparkles" class="size-5" x-show="! selected" />
                                        @foreach ($siteCategories as $category)
                                            <x-icon :name="$category->icon ?: 'map-pin'" class="size-5" x-show="selected?.slug === '{{ $category->slug }}'" x-cloak />
                                        @endforeach
                                    </span>
                                    <span class="min-w-0 flex-1">
                                        <span class="block text-[11px] font-semibold uppercase tracking-wider text-slate-500">I'm into</span>
                                        <span class="block truncate text-sm font-semibold text-slate-900" x-text="selected ? selected.name : 'A bit of everything'">A bit of everything</span>
                                    </span>
                                    <x-icon name="chevron-down" class="size-4 text-slate-400 transition" ::class="open && 'rotate-180'" />
                                </button>
                                <ul id="interest-list" role="listbox" aria-label="Interest" x-show="open" x-cloak x-ref="list" @click.outside="open = false" tabindex="-1"
                                    @keydown.arrow-down.prevent="move(1)" @keydown.arrow-up.prevent="move(-1)" @keydown.enter.prevent="choose(active)" @keydown.tab="close()"
                                    x-transition:enter="transition ease-out duration-150" x-transition:enter-start="opacity-0 translate-y-1" x-transition:enter-end="opacity-100 translate-y-0"
                                    class="absolute start-0 top-full z-30 mt-3 w-72 rounded-2xl bg-white p-2 shadow-2xl shadow-primary-950/15 ring-1 ring-primary-950/5 focus:outline-none">
                                    <li role="option" :aria-selected="(! selected).toString()" @click="choose(-1)" @mouseenter="active = -1"
                                        :class="active === -1 && 'bg-sand-100'" class="flex cursor-pointer items-center gap-3 rounded-xl px-3 py-2 text-sm text-slate-800">
                                        <x-icon name="sparkles" class="size-5 text-primary-700" /> A bit of everything
                                    </li>
                                    @foreach ($siteCategories as $i => $category)
                                        <li role="option" :aria-selected="(selected?.slug === '{{ $category->slug }}').toString()" @click="choose({{ $i }})" @mouseenter="active = {{ $i }}"
                                            :class="active === {{ $i }} && 'bg-sand-100'" class="flex cursor-pointer items-center gap-3 rounded-xl px-3 py-2 text-sm text-slate-800">
                                            <x-icon :name="$category->icon ?: 'map-pin'" class="size-5 text-primary-700" /> {{ $category->name }}
                                            <x-icon name="check" class="ms-auto size-4 text-primary-700" x-show="selected?.slug === '{{ $category->slug }}'" x-cloak />
                                        </li>
                                    @endforeach
                                </ul>
                            </div>
                            <button type="submit" class="group inline-flex items-center justify-center gap-2 rounded-full bg-primary-800 px-6 py-3.5 text-sm font-semibold text-white transition hover:bg-primary-900 focus:outline-none focus-visible:ring-2 focus-visible:ring-primary-500 focus-visible:ring-offset-2">
                                Start planning <x-icon name="arrow-right" class="size-4 transition group-hover:translate-x-1" />
                            </button>
                        </form>
                        <div class="mt-5 flex flex-wrap items-center gap-x-6 gap-y-2 text-sm font-medium text-slate-700" data-hero-line>
                            <a href="{{ route('packages.index') }}" class="group inline-flex items-center gap-2 hover:text-primary-800">
                                <span class="grid size-9 place-items-center rounded-full bg-white/80 text-primary-800 ring-1 ring-primary-900/10 transition group-hover:bg-primary-800 group-hover:text-white"><x-icon name="gift" class="size-4" /></span>
                                Ready-made packages
                            </a>
                            <a href="{{ route('destinations.index', ['gems' => 1]) }}" class="group inline-flex items-center gap-2 hover:text-primary-800">
                                <span class="grid size-9 place-items-center rounded-full bg-white/80 text-primary-800 ring-1 ring-primary-900/10 transition group-hover:bg-primary-800 group-hover:text-white"><x-icon name="gem" class="size-4" /></span>
                                Hidden gems
                            </a>
                        </div>
                    </div>

                    {{-- Right: numbers + the place on screen --}}
                    @if ($heroPlaces->isNotEmpty())
                        <div class="flex flex-col justify-between gap-6 lg:col-span-5 lg:items-end xl:col-span-6">
                            <div class="hidden items-center gap-3 rounded-full bg-white/80 py-2 ps-2 pe-5 ring-1 ring-primary-900/10 backdrop-blur lg:flex" data-hero-line>
                                <div class="flex -space-x-3">
                                    @foreach ($topPlaces->take(3) as $place)
                                        <x-media-img :media="$place->cover" :width="400" sizes="48px" alt="" class="size-11 rounded-full object-cover ring-2 ring-white" />
                                    @endforeach
                                </div>
                                <p class="leading-tight"><span class="block text-xl font-extrabold text-primary-950">{{ number_format($stats['places']) }}+</span><span class="text-xs text-slate-600">places to explore</span></p>
                            </div>

                            <article class="w-full rounded-[1.75rem] bg-white/85 p-3 shadow-2xl shadow-primary-950/20 ring-1 ring-white/60 backdrop-blur-md sm:max-w-xs" data-hero-card aria-roledescription="carousel" aria-label="Featured places">
                                <div class="relative aspect-[4/3] overflow-hidden rounded-2xl bg-primary-100">
                                    @foreach ($heroPlaces as $i => $place)
                                        <div x-show="index === {{ $i }}" @if ($i > 0) x-cloak @endif x-transition.opacity.duration.500ms class="absolute inset-0">
                                            <x-media-img :media="$place->cover" :width="800" sizes="320px" :alt="$place->name" class="size-full object-cover" />
                                        </div>
                                    @endforeach
                                    <span class="absolute start-3 top-3 rounded-full bg-primary-900/85 px-3 py-1 text-[11px] font-semibold text-white backdrop-blur">Featured place</span>
                                    <div class="absolute end-3 top-3 flex gap-1.5">
                                        <button type="button" @click="prev()" class="grid size-9 place-items-center rounded-full bg-white/90 text-primary-900 shadow transition hover:bg-white"><span class="sr-only">Previous place</span><x-icon name="chevron-left" class="size-4" /></button>
                                        <button type="button" @click="next()" class="grid size-9 place-items-center rounded-full bg-white/90 text-primary-900 shadow transition hover:bg-white"><span class="sr-only">Next place</span><x-icon name="chevron-right" class="size-4" /></button>
                                    </div>
                                </div>
                                <div class="relative min-h-[7.5rem] px-2 pt-4" aria-live="polite">
                                    @foreach ($heroPlaces as $i => $place)
                                        <div x-show="index === {{ $i }}" @if ($i > 0) x-cloak @endif x-transition:enter="transition duration-500" x-transition:enter-start="opacity-0 translate-y-2" x-transition:enter-end="opacity-100 translate-y-0">
                                            <h2 class="text-2xl font-semibold tracking-tight text-primary-950"><a href="{{ $place->url() }}" class="hover:underline">{{ $place->name }}</a></h2>
                                            <p class="mt-1 text-sm text-slate-600">{{ $place->district->name }} · {{ $place->categories->first()?->name ?? 'Sri Lanka' }}</p>
                                            <p class="mt-2 truncate text-[10px] text-slate-500"><x-photo-credit :media="$place->cover" class="text-[10px]" /></p>
                                        </div>
                                    @endforeach
                                </div>
                                <div class="flex gap-1.5 px-2 pb-2" role="tablist" aria-label="Choose a place">
                                    @foreach ($heroPlaces as $i => $place)
                                        <button type="button" role="tab" @click="go({{ $i }})" :aria-selected="(index === {{ $i }}).toString()" class="relative h-1.5 flex-1 overflow-hidden rounded-full bg-primary-900/10">
                                            <span class="sr-only">{{ $place->name }}</span>
                                            <span class="absolute inset-y-0 start-0 rounded-full bg-primary-700" :style="`width: ${index === {{ $i }} ? progress : (index > {{ $i }} ? 100 : 0)}%`"></span>
                                        </button>
                                    @endforeach
                                </div>
                            </article>
                        </div>
                    @endif
                </div>

                {{-- Bottom row of cards over the photo --}}
                <div class="relative grid gap-3 p-3 sm:p-4 lg:grid-cols-12" data-hero-cards>
                    <a href="{{ route('plan') }}" class="spotlight group flex flex-col justify-between gap-8 overflow-hidden rounded-3xl bg-primary-900/95 p-6 text-white backdrop-blur lg:col-span-3">
                        <span class="flex items-start justify-between">
                            <span class="grid size-14 place-items-center rounded-full ring-1 ring-white/25"><x-icon name="map" class="size-6 text-accent-300" /></span>
                            <x-icon name="arrow-up-right" class="size-5 text-white/70 transition group-hover:translate-x-0.5 group-hover:-translate-y-0.5 group-hover:text-white" />
                        </span>
                        <span>
                            <span class="block text-2xl font-semibold leading-tight">Build your own trip</span>
                            <span class="mt-2 block text-sm text-primary-100/80">8 easy steps, a route map and a full price breakdown.</span>
                        </span>
                    </a>

                    <div class="rounded-3xl bg-sand-50/95 p-5 backdrop-blur lg:col-span-6">
                        <div class="flex items-center justify-between gap-3">
                            <h2 class="text-lg font-semibold text-primary-950">Handpicked destinations</h2>
                            <a href="{{ route('destinations.index') }}" class="group inline-flex items-center gap-2 text-sm font-medium text-slate-700 hover:text-primary-900">View all <span class="grid size-7 place-items-center rounded-full bg-white ring-1 ring-primary-900/10 transition group-hover:rotate-45"><x-icon name="arrow-up-right" class="size-3.5" /></span></a>
                        </div>
                        <div class="mt-4 grid grid-cols-3 gap-3">
                            @foreach ($topPlaces->slice(3, 3) as $place)
                                <a href="{{ $place->url() }}" class="group block">
                                    <span class="block aspect-[4/3] overflow-hidden rounded-2xl bg-primary-100">
                                        <x-media-img :media="$place->cover" :width="400" sizes="(min-width: 1024px) 15vw, 30vw" :alt="$place->name" class="size-full object-cover transition duration-500 group-hover:scale-110" />
                                    </span>
                                    <span class="mt-2 block truncate text-sm font-semibold text-slate-900">{{ $place->name }}</span>
                                    <span class="block truncate text-xs text-slate-500">{{ $place->district->name }}</span>
                                </a>
                            @endforeach
                        </div>
                    </div>

                    <div class="spotlight overflow-hidden rounded-3xl bg-primary-900/95 p-6 text-white backdrop-blur lg:col-span-3">
                        <x-icon name="globe" class="size-9 text-accent-300" />
                        <h2 class="mt-5 text-xl font-semibold leading-tight text-white">Seamless from start to finish</h2>
                        <ul class="mt-4 space-y-2 text-sm text-primary-100/90">
                            @foreach (['Day-by-day itinerary', 'A local agent checks it', 'Prices in rupees', 'Printable e-receipt'] as $point)
                                <li class="flex items-center gap-2"><span class="grid size-5 place-items-center rounded-full bg-accent-400 text-primary-950"><x-icon name="check" class="size-3" /></span>{{ $point }}</li>
                            @endforeach
                        </ul>
                    </div>
                </div>
            </div>
        </section>

        {{-- ================================================================ Marquee of places --}}
        @if ($placeNames->isNotEmpty())
            <div class="marquee overflow-hidden py-8" aria-hidden="true">
                <div class="marquee-track flex w-max items-center gap-8 whitespace-nowrap">
                    @foreach ([1, 2] as $copy)
                        @foreach ($placeNames as $name)
                            <span class="font-display text-3xl italic text-primary-900/70 sm:text-4xl">{{ $name }}</span>
                            <x-icon name="sparkles" class="size-5 text-accent-500" />
                        @endforeach
                    @endforeach
                </div>
            </div>
        @endif

        {{-- ================================================================ Interests: expanding panels --}}
        <section class="mx-auto max-w-7xl px-4 py-16 sm:px-6 lg:px-8" aria-labelledby="interests-heading">
            <x-home.heading id="interests-heading" eyebrow="Interests" title="Explore Sri Lanka by" accent="interest" :link="route('destinations.index')" linkLabel="All destinations" />

            <div class="-mx-4 flex snap-x snap-mandatory gap-3 overflow-x-auto px-4 pb-4 lg:mx-0 lg:h-[32rem] lg:snap-none lg:overflow-visible lg:px-0 lg:pb-0" x-data="{ active: 0 }" data-reveal>
                @foreach ($categoryTiles as $i => $tile)
                    @php($category = $tile['category'])
                    <div @mouseenter="active = {{ $i }}" @focusin="active = {{ $i }}"
                         :class="active === {{ $i }} ? 'lg:flex-[5]' : 'lg:flex-[1]'"
                         class="group relative isolate flex h-96 w-72 shrink-0 snap-start flex-col justify-end overflow-hidden rounded-3xl bg-primary-800 text-white transition-[flex-grow] duration-700 ease-[cubic-bezier(0.2,0.8,0.2,1)] lg:h-auto lg:w-auto lg:min-w-0 lg:shrink">
                        @if ($tile['cover'])
                            <x-media-img :media="$tile['cover']" sizes="(min-width: 1024px) 50vw, 288px" alt="" class="absolute inset-0 -z-20 size-full object-cover transition duration-700 group-hover:scale-105" />
                        @endif
                        <span class="absolute inset-0 -z-10 bg-gradient-to-t from-primary-950/90 via-primary-950/30 to-transparent"></span>

                        {{-- Collapsed label (desktop) --}}
                        <span :class="active === {{ $i }} ? 'lg:opacity-0' : 'lg:opacity-100'" class="pointer-events-none absolute inset-x-0 bottom-6 hidden flex-col items-center gap-3 transition-opacity duration-300 lg:flex">
                            <span class="grid size-11 place-items-center rounded-full bg-white/15 backdrop-blur"><x-icon :name="$category->icon ?: 'map-pin'" class="size-5" /></span>
                            <span class="font-display text-2xl italic [writing-mode:vertical-rl] rotate-180">{{ $category->name }}</span>
                        </span>

                        {{-- Expanded content --}}
                        <div :class="active === {{ $i }} ? 'lg:opacity-100 lg:translate-y-0' : 'lg:pointer-events-none lg:opacity-0 lg:translate-y-4'" class="p-6 transition duration-500 lg:p-8">
                            <span class="inline-flex items-center gap-2 rounded-full bg-white/15 px-3 py-1 text-xs font-semibold backdrop-blur">
                                <x-icon :name="$category->icon ?: 'map-pin'" class="size-4" /> {{ $tile['count'] }} {{ Str::plural('place', $tile['count']) }}
                            </span>
                            <h3 class="mt-3 font-display text-4xl leading-none text-white lg:text-5xl">{{ $category->name }}</h3>
                            <p class="mt-3 max-w-sm text-sm text-white/85">{{ $taglines[$category->icon] ?? 'See the places we love for this.' }}</p>
                            <div class="mt-5 flex flex-wrap gap-2">
                                <a href="{{ route('destinations.index', ['category' => $category->slug]) }}" class="inline-flex items-center gap-1.5 rounded-full bg-white px-4 py-2 text-sm font-semibold text-primary-900 transition hover:bg-accent-300">See places <x-icon name="arrow-right" class="size-4" /></a>
                                <a href="{{ route('plan', ['category' => $category->slug]) }}" class="inline-flex items-center gap-1.5 rounded-full px-4 py-2 text-sm font-semibold text-white ring-1 ring-white/40 transition hover:bg-white/10">Plan a trip</a>
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>
        </section>

        {{-- ================================================================ Top destinations: 3D tilt cards in a carousel --}}
        @if ($topPlaces->isNotEmpty())
            <section class="overflow-hidden bg-sand-100 py-20" aria-labelledby="top-heading">
                <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8" x-data="cardCarousel()">
                    <x-home.heading id="top-heading" eyebrow="Favourites" title="Places travellers" accent="love most" subtitle="Drag, swipe or use the arrows. Every card opens the full guide with photos, timings and tickets.">
                        <div class="flex gap-2">
                            <button type="button" x-ref="prev" class="grid size-12 place-items-center rounded-full bg-white text-primary-900 ring-1 ring-primary-900/10 transition hover:bg-primary-800 hover:text-white disabled:opacity-40"><span class="sr-only">Previous</span><x-icon name="chevron-left" class="size-5" /></button>
                            <button type="button" x-ref="next" class="grid size-12 place-items-center rounded-full bg-primary-800 text-white transition hover:bg-primary-900 disabled:opacity-40"><span class="sr-only">Next</span><x-icon name="chevron-right" class="size-5" /></button>
                        </div>
                    </x-home.heading>

                    <div class="swiper -mx-3! -my-6! px-3! py-6!" x-ref="swiper" data-reveal>
                        <div class="swiper-wrapper">
                            @foreach ($topPlaces as $place)
                                <div class="swiper-slide h-auto!">
                                    <a href="{{ $place->url() }}" class="tilt-card spotlight group relative isolate flex aspect-[3/4] flex-col justify-end overflow-hidden rounded-3xl bg-primary-800 p-5 text-white shadow-xl shadow-primary-950/10">
                                        <x-media-img :media="$place->cover" :width="800" sizes="(min-width: 1280px) 25vw, (min-width: 640px) 45vw, 85vw" :alt="$place->name" class="absolute inset-0 -z-20 size-full object-cover transition duration-700 group-hover:scale-110" />
                                        <span class="absolute inset-0 -z-10 bg-gradient-to-t from-primary-950/90 via-primary-950/20 to-transparent"></span>
                                        <span class="tilt-pop absolute start-4 top-4 flex flex-wrap gap-1.5">
                                            @foreach ($place->categories->take(1) as $category)
                                                <span class="rounded-full bg-white/90 px-2.5 py-1 text-[11px] font-semibold text-primary-900">{{ $category->name }}</span>
                                            @endforeach
                                            @if ($place->is_hidden_gem)<span class="rounded-full bg-accent-500 px-2.5 py-1 text-[11px] font-bold text-primary-950">Hidden gem</span>@endif
                                        </span>
                                        <span class="tilt-pop">
                                            <span class="flex items-center gap-1 text-xs text-white/80"><x-icon name="map-pin" class="size-3.5" /> {{ $place->district->name }}</span>
                                            <span class="mt-1 block text-2xl font-semibold leading-tight">{{ $place->name }}</span>
                                            <span class="mt-3 flex items-center justify-between text-xs text-white/80">
                                                <span class="inline-flex items-center gap-1"><x-icon name="clock" class="size-3.5" /> {{ $place->visitDuration() }}</span>
                                                <span class="grid size-9 place-items-center rounded-full bg-white text-primary-900 transition group-hover:rotate-45 group-hover:bg-accent-400"><x-icon name="arrow-up-right" class="size-4" /></span>
                                            </span>
                                        </span>
                                    </a>
                                </div>
                            @endforeach
                        </div>
                    </div>
                </div>
            </section>
        @endif

        {{-- ================================================================ How it works: sticky steps --}}
        <section class="mx-auto max-w-7xl px-4 py-24 sm:px-6 lg:px-8" aria-labelledby="how-heading" x-data="{ active: 0 }" data-steps>
            <div class="grid gap-12 lg:grid-cols-2 lg:gap-16">
                <div class="lg:sticky lg:top-28 lg:self-start">
                    <x-home.heading id="how-heading" eyebrow="How it works" title="Your trip in four" accent="easy steps" class="mb-8!" />
                    <ol class="space-y-2">
                        @foreach ([
                            ['Choose your style', 'Budget, Premium or Luxury, then the interests and districts you want to see.'],
                            ['Pick your places', 'Tap places on the map or in the list. Add hidden gems, remove anything with one click.'],
                            ['Get a day-by-day plan', 'We arrange your places by route and drive time, with hotels, meals, vehicle and guide.'],
                            ['An agent confirms it', 'Submit in one click. A local agent checks every detail, then you get a printable e-receipt.'],
                        ] as $i => [$heading, $text])
                            <li>
                                <button type="button" @click="document.querySelector('[data-step=\'{{ $i }}\']')?.scrollIntoView({ behavior: 'smooth', block: 'center' })"
                                        :class="active === {{ $i }} ? 'bg-white shadow-lg shadow-primary-950/5 ring-primary-900/10' : 'ring-transparent hover:bg-white/60'"
                                        class="flex w-full gap-4 rounded-2xl p-4 text-start ring-1 transition">
                                    <span :class="active === {{ $i }} ? 'bg-primary-800 text-white' : 'bg-sand-200 text-primary-900'" class="grid size-10 shrink-0 place-items-center rounded-full text-sm font-bold transition">{{ $i + 1 }}</span>
                                    <span>
                                        <span class="block text-lg font-semibold text-primary-950">{{ $heading }}</span>
                                        <span class="block text-sm text-slate-600">{{ $text }}</span>
                                    </span>
                                </button>
                            </li>
                        @endforeach
                    </ol>
                    <a href="{{ route('plan') }}" class="group mt-8 inline-flex items-center gap-2 rounded-full bg-primary-800 py-2 ps-6 pe-2 text-sm font-semibold text-white transition hover:bg-primary-900">
                        Start planning now <span class="grid size-9 place-items-center rounded-full bg-accent-500 text-primary-950 transition group-hover:rotate-45"><x-icon name="arrow-up-right" class="size-4" /></span>
                    </a>
                </div>

                <div class="space-y-6 lg:space-y-[20vh] lg:py-[10vh]">
                    {{-- 1. Style --}}
                    <div data-step="0" class="rounded-[2rem] bg-white p-6 shadow-xl shadow-primary-950/5 ring-1 ring-primary-900/5 sm:p-8">
                        <p class="text-sm font-semibold text-primary-700">Step 1 · Travel style</p>
                        <div class="mt-5 grid gap-3 sm:grid-cols-3">
                            @foreach ([['Budget', 'Guesthouses, car with driver'], ['Premium', '3–4 star hotels, half board'], ['Luxury', 'Boutique villas, private guide']] as $j => [$tier, $hint])
                                <div @class(['rounded-2xl p-4 ring-1', 'bg-primary-800 text-white ring-primary-800' => $j === 1, 'bg-sand-50 ring-sand-200' => $j !== 1])>
                                    <x-icon :name="['sparkles', 'star', 'gem'][$j]" :class="$j === 1 ? 'size-6 text-accent-300' : 'size-6 text-primary-700'" />
                                    <p class="mt-3 font-semibold">{{ $tier }}</p>
                                    <p @class(['text-xs', 'text-primary-100' => $j === 1, 'text-slate-500' => $j !== 1])>{{ $hint }}</p>
                                </div>
                            @endforeach
                        </div>
                        <div class="mt-4 flex flex-wrap gap-2">
                            @foreach ($siteCategories as $j => $category)
                                <span @class(['inline-flex items-center gap-1.5 rounded-full px-3 py-1.5 text-xs font-medium', 'bg-primary-50 text-primary-800 ring-1 ring-primary-200' => $j < 3, 'bg-sand-100 text-slate-600' => $j >= 3])>
                                    <x-icon :name="$category->icon ?: 'map-pin'" class="size-3.5" /> {{ $category->name }}
                                </span>
                            @endforeach
                        </div>
                    </div>

                    {{-- 2. Places --}}
                    <div data-step="1" class="rounded-[2rem] bg-white p-6 shadow-xl shadow-primary-950/5 ring-1 ring-primary-900/5 sm:p-8">
                        <p class="text-sm font-semibold text-primary-700">Step 2 · Places</p>
                        <div class="mt-5 grid grid-cols-2 gap-3">
                            @foreach ($topPlaces->take(4) as $j => $place)
                                <div class="overflow-hidden rounded-2xl bg-sand-50 ring-1 ring-sand-200">
                                    <x-media-img :media="$place->cover" :width="400" sizes="(min-width: 1024px) 18vw, 45vw" alt="" class="aspect-[16/10] w-full object-cover" />
                                    <div class="flex items-center justify-between gap-2 p-3">
                                        <span class="truncate text-sm font-semibold text-slate-900">{{ $place->name }}</span>
                                        <span @class(['grid size-7 shrink-0 place-items-center rounded-full text-xs', 'bg-primary-700 text-white' => $j < 3, 'bg-white text-primary-700 ring-1 ring-primary-200' => $j >= 3])>
                                            <x-icon :name="$j < 3 ? 'check' : 'sparkles'" class="size-3.5" />
                                        </span>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </div>

                    {{-- 3. Plan --}}
                    <div data-step="2" class="rounded-[2rem] bg-white p-6 shadow-xl shadow-primary-950/5 ring-1 ring-primary-900/5 sm:p-8">
                        <p class="text-sm font-semibold text-primary-700">Step 3 · Day by day</p>
                        <ol class="relative mt-6 space-y-5 border-s-2 border-dashed border-primary-200 ps-6">
                            @foreach ($heroPlaces->take(4) as $j => $place)
                                <li class="relative">
                                    <span class="absolute -start-[2.05rem] top-0.5 grid size-5 place-items-center rounded-full bg-primary-700 ring-4 ring-white"><span class="size-1.5 rounded-full bg-white"></span></span>
                                    <p class="text-xs font-semibold uppercase tracking-wider text-slate-500">Day {{ $j + 1 }}</p>
                                    <p class="font-semibold text-slate-900">{{ $place->name }}</p>
                                    <p class="flex items-center gap-1 text-xs text-slate-500"><x-icon name="map-pin" class="size-3.5" /> {{ $place->district->name }} · {{ $place->visitDuration() }}</p>
                                </li>
                            @endforeach
                        </ol>
                    </div>

                    {{-- 4. Confirmed --}}
                    <div data-step="3" class="relative overflow-hidden rounded-[2rem] bg-primary-900 p-6 text-white shadow-xl shadow-primary-950/10 sm:p-8">
                        <span class="absolute -end-10 -top-10 size-40 rounded-full bg-accent-500/25 blur-3xl"></span>
                        <p class="text-sm font-semibold text-accent-300">Step 4 · Confirmed</p>
                        <div class="mt-5 flex items-start justify-between gap-6">
                            <div>
                                <p class="font-display text-4xl italic">Booking confirmed</p>
                                <p class="mt-2 text-sm text-primary-100/80">Your agent finalises the timings and sends a printable e-receipt with a QR code anyone can verify.</p>
                                <p class="mt-4 inline-flex items-center gap-2 rounded-full bg-white/10 px-3 py-1.5 font-mono text-xs">LG360-R-{{ now()->year }}-00001</p>
                            </div>
                            <div class="grid size-24 shrink-0 grid-cols-5 gap-1 rounded-xl bg-white p-2" aria-hidden="true">
                                @foreach (str_split('1101101010011101001011101') as $bit)
                                    <span @class(['rounded-[2px]', 'bg-primary-950' => $bit === '1'])></span>
                                @endforeach
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        {{-- ================================================================ Packages: focus cards --}}
        @if ($packages->isNotEmpty() && Route::has('packages.show'))
            <section class="bg-primary-950 py-24" aria-labelledby="packages-heading">
                <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                    <x-home.heading id="packages-heading" eyebrow="Ready-made" title="Trips planned by" accent="our team" subtitle="Book a proven route as it is, or open it in the Trip Builder and make it yours." :link="route('packages.index')" linkLabel="All packages" :dark="true" />
                    <div @class(["group/list grid gap-5 sm:grid-cols-2", "lg:grid-cols-4" => $packages->count() === 4, "lg:grid-cols-3" => $packages->count() !== 4]) data-reveal>
                        @foreach ($packages as $package)
                            @php($cover = $package->coverPhoto())
                            <article class="spotlight group relative isolate flex aspect-[4/5] flex-col justify-end overflow-hidden rounded-3xl bg-primary-800 p-6 text-white transition duration-500 lg:group-hover/list:scale-[0.98] lg:group-hover/list:opacity-50 lg:group-hover/list:blur-[2px] lg:hover:scale-100! lg:hover:opacity-100! lg:hover:blur-none!">
                                @if ($cover)
                                    <x-media-img :media="$cover" sizes="(min-width: 1024px) 33vw, (min-width: 640px) 50vw, 100vw" :alt="$package->name" class="absolute inset-0 -z-20 size-full object-cover transition duration-700 group-hover:scale-105" />
                                @endif
                                <span class="absolute inset-0 -z-10 bg-gradient-to-t from-primary-950 via-primary-950/50 to-primary-950/10"></span>
                                <div class="absolute inset-x-5 top-5 flex items-center justify-between">
                                    @if ($package->tier)<span class="rounded-full bg-white/90 px-3 py-1 text-xs font-bold text-primary-900">{{ $package->tier->label() }}</span>@endif
                                    @if ($package->days)<span class="inline-flex items-center gap-1 rounded-full bg-primary-950/60 px-3 py-1 text-xs font-semibold backdrop-blur"><x-icon name="calendar" class="size-3.5" /> {{ $package->days }} days</span>@endif
                                </div>
                                <h3 class="font-display text-3xl leading-tight text-white"><a href="{{ route('packages.show', $package) }}" class="after:absolute after:inset-0 after:-z-0 focus:outline-none">{{ $package->name }}</a></h3>
                                @if ($package->summary)<p class="mt-2 line-clamp-2 text-sm text-white/80">{{ $package->summary }}</p>@endif
                                <div class="relative mt-5 flex items-center justify-between gap-3">
                                    @if ($package->from_price)
                                        <p class="text-xs text-white/70">from<span class="block text-xl font-bold text-white">@money($package->from_price)</span>per person</p>
                                    @endif
                                    @if (Route::has('packages.customize'))
                                        <form method="POST" action="{{ route('packages.customize', $package) }}" class="relative z-10">
                                            @csrf
                                            <button type="submit" class="rounded-full bg-white px-4 py-2 text-sm font-semibold text-primary-900 transition hover:bg-accent-400">Customize</button>
                                        </form>
                                    @endif
                                </div>
                            </article>
                        @endforeach
                    </div>
                </div>
            </section>
        @endif

        {{-- ================================================================ Parallax band with numbers --}}
        <section class="relative isolate overflow-hidden py-28 text-white" aria-label="LankaGuide360 in numbers">
            @if ($heroPlaces->count() > 1)
                <div class="absolute inset-0 -z-20">
                    <x-media-img :media="$heroPlaces[1]->cover" :width="1600" sizes="100vw" alt="" class="h-[135%] w-full object-cover" data-parallax="-18" />
                </div>
            @endif
            <div class="absolute inset-0 -z-10 bg-primary-950/75"></div>
            <div class="mx-auto max-w-5xl px-4 text-center sm:px-6">
                <p class="font-display text-4xl leading-tight italic sm:text-6xl" data-reveal>“One island, a thousand landscapes, planned in minutes.”</p>
                <dl class="mt-14 grid grid-cols-2 gap-8 lg:grid-cols-4" data-reveal>
                    @foreach ([['places', 'Places to visit'], ['districts', 'Districts covered'], ['hotels', 'Hotels & stays'], ['trips', 'Trips planned']] as [$key, $label])
                        <div x-data="countUp({{ (int) $stats[$key] }})" class="rounded-3xl bg-white/10 p-6 ring-1 ring-white/15 backdrop-blur">
                            <dt class="text-sm text-primary-100/80">{{ $label }}</dt>
                            <dd class="mt-1 text-4xl font-extrabold text-white sm:text-5xl"><span x-text="formatted">{{ number_format($stats[$key]) }}</span></dd>
                        </div>
                    @endforeach
                </dl>
            </div>
        </section>

        {{-- ================================================================ Hidden gems --}}
        @if ($hiddenGems->isNotEmpty())
            <section class="overflow-hidden py-20" aria-labelledby="gems-heading">
                <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8" x-data="cardCarousel()">
                    <x-home.heading id="gems-heading" eyebrow="Off the beaten track" title="Hidden" accent="gems" subtitle="Quiet places most visitors miss." :link="route('destinations.index', ['gems' => 1])">
                        <span class="hidden" x-ref="prev"></span><span class="hidden" x-ref="next"></span>
                    </x-home.heading>
                    <div class="swiper -mx-3! -my-6! px-3! py-6!" x-ref="swiper">
                        <div class="swiper-wrapper">
                            @foreach ($hiddenGems as $place)
                                <div class="swiper-slide h-auto!"><x-place-card :place="$place" class="tilt-card h-full" sizes="(min-width: 1280px) 25vw, (min-width: 640px) 45vw, 85vw" /></div>
                            @endforeach
                        </div>
                    </div>
                </div>
            </section>
        @endif

        {{-- ================================================================ Testimonials --}}
        @if ($reviews->isNotEmpty())
            <section class="bg-sand-100 py-20" aria-labelledby="reviews-heading">
                <div class="mx-auto max-w-5xl px-4 sm:px-6 lg:px-8" x-data="carousel({{ $reviews->count() }})" @mouseenter="pause()" @mouseleave="play()" @focusin="pause()">
                    <x-home.heading id="reviews-heading" eyebrow="Reviews" title="What travellers" accent="say" class="justify-center! text-center sm:flex-col sm:items-center" />
                    <div class="relative min-h-64 rounded-[2rem] bg-white p-8 shadow-xl shadow-primary-950/5 sm:p-12" aria-live="polite">
                        <span class="absolute start-6 top-2 font-display text-9xl leading-none text-primary-100" aria-hidden="true">“</span>
                        @foreach ($reviews as $i => $review)
                            <figure x-show="index === {{ $i }}" @if ($i > 0) x-cloak @endif x-transition:enter="transition duration-500" x-transition:enter-start="opacity-0 translate-y-3" x-transition:enter-end="opacity-100 translate-y-0" class="relative">
                                <p class="text-accent-500" aria-label="{{ $review->rating }} out of 5 stars">{{ str_repeat('★', $review->rating) }}</p>
                                <blockquote class="mt-4 font-display text-2xl leading-snug text-primary-950 sm:text-3xl">“{{ $review->comment }}”</blockquote>
                                <figcaption class="mt-6 flex items-center gap-3">
                                    <span class="grid size-11 place-items-center rounded-full bg-primary-800 font-bold text-white">{{ Str::upper(Str::substr($review->author_name, 0, 1)) }}</span>
                                    <span class="text-sm"><span class="block font-semibold text-slate-900">{{ $review->author_name }}</span>@if ($review->country)<span class="text-slate-500">{{ $review->country }}</span>@endif</span>
                                </figcaption>
                            </figure>
                        @endforeach
                        @if ($reviews->count() > 1)
                            <div class="mt-8 flex items-center justify-end gap-2">
                                <button type="button" @click="prev()" class="grid size-11 place-items-center rounded-full ring-1 ring-primary-900/15 hover:bg-sand-100"><span class="sr-only">Previous review</span><x-icon name="chevron-left" class="size-4" /></button>
                                <button type="button" @click="next()" class="grid size-11 place-items-center rounded-full bg-primary-800 text-white hover:bg-primary-900"><span class="sr-only">Next review</span><x-icon name="chevron-right" class="size-4" /></button>
                            </div>
                        @endif
                    </div>
                </div>
            </section>
        @endif

        {{-- ================================================================ Call to action + newsletter --}}
        <section class="px-2 pb-2 sm:px-4 sm:pb-4" aria-labelledby="cta-heading">
            <div class="spotlight relative isolate overflow-hidden rounded-[1.75rem] bg-primary-900 px-6 py-16 text-white sm:rounded-[2.5rem] sm:px-12 lg:py-24">
                <span class="absolute -start-20 -top-24 -z-10 size-80 rounded-full bg-primary-600/50 blur-3xl"></span>
                <span class="absolute -end-16 -bottom-24 -z-10 size-80 rounded-full bg-accent-500/30 blur-3xl"></span>
                <div class="mx-auto grid max-w-6xl items-center gap-12 lg:grid-cols-2">
                    <div data-reveal>
                        <h2 id="cta-heading" class="text-5xl font-bold leading-[1] tracking-tight text-white sm:text-6xl">Your Sri Lanka story <span class="block font-display font-normal italic text-accent-300">starts here.</span></h2>
                        <div class="mt-8 flex flex-wrap gap-3">
                            <a href="{{ route('plan') }}" class="group inline-flex items-center gap-2 rounded-full bg-accent-500 py-2 ps-6 pe-2 font-semibold text-primary-950 transition hover:bg-accent-400">
                                Plan my trip <span class="grid size-9 place-items-center rounded-full bg-primary-950 text-white transition group-hover:rotate-45"><x-icon name="arrow-up-right" class="size-4" /></span>
                            </a>
                            <a href="{{ route('contact') }}" class="inline-flex items-center rounded-full px-6 py-3 font-semibold text-white ring-1 ring-white/30 hover:bg-white/10">Talk to an agent</a>
                        </div>
                    </div>
                    <div class="rounded-3xl bg-white/10 p-6 ring-1 ring-white/15 backdrop-blur sm:p-8" data-reveal>
                        <h3 class="text-xl font-semibold text-white">Travel tips, once a month</h3>
                        <p class="mt-1 text-sm text-primary-100/80">Best seasons, new hidden gems and practical advice. No spam.</p>
                        <form method="POST" action="{{ route('newsletter.store') }}" class="mt-5 flex flex-col gap-2 sm:flex-row">
                            @csrf
                            <label for="home-newsletter" class="sr-only">Email address</label>
                            <input id="home-newsletter" type="email" name="email" required autocomplete="email" placeholder="you@example.com" class="min-w-0 flex-1 rounded-full border-white/20 bg-white/10 px-5 py-3 text-sm text-white placeholder:text-primary-100/50 focus:border-accent-400 focus:ring-accent-400">
                            <button type="submit" class="rounded-full bg-white px-6 py-3 text-sm font-semibold text-primary-900 hover:bg-accent-300">Subscribe</button>
                        </form>
                        @if (session('newsletter'))
                            <p class="mt-3 text-sm text-accent-300" role="status">{{ session('newsletter') }}</p>
                        @endif
                        @error('email')<p class="mt-3 text-sm text-red-300">{{ $message }}</p>@enderror
                    </div>
                </div>
            </div>
        </section>
    </div>
</x-app-layout>
