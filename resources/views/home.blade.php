<x-app-layout :image="$hero?->cover?->url(1600)" description="Build your own day-by-day Sri Lanka trip in minutes: beaches, ancient cities, tea country and wildlife, with hotels, transport and a full price, confirmed by a local travel agent.">
    {{-- Hero --}}
    <section class="relative isolate overflow-hidden bg-primary-900 text-white">
        @if ($hero?->cover)
            <x-media-img :media="$hero->cover" :width="1600" :eager="true" sizes="100vw" alt="" class="absolute inset-0 -z-20 size-full object-cover" />
            <div class="absolute inset-0 -z-10 bg-gradient-to-r from-slate-950/85 via-slate-950/55 to-slate-950/20"></div>
        @else
            <div class="absolute inset-0 -z-10 bg-[radial-gradient(ellipse_at_top_right,_var(--color-primary-500)_0%,_transparent_55%),radial-gradient(ellipse_at_bottom_left,_var(--color-accent-500)_0%,_transparent_45%)] opacity-60"></div>
        @endif
        <div class="mx-auto flex min-h-[min(80vh,56rem)] max-w-7xl flex-col justify-center px-4 py-20 sm:px-6 lg:px-8">
            <p class="text-sm font-semibold uppercase tracking-widest text-accent-300">Sri Lanka trip planner</p>
            <h1 class="mt-3 max-w-3xl text-4xl font-extrabold tracking-tight text-white sm:text-6xl">Plan your perfect Sri Lanka journey</h1>
            <p class="mt-5 max-w-2xl text-lg text-slate-100">
                Pick your travel style, the places you love and your dates. We build a day-by-day plan with a route map
                and a full price breakdown, and a local agent confirms it.
            </p>
            <div class="mt-8 flex flex-wrap gap-3">
                <a href="{{ route('plan') }}" class="inline-flex items-center gap-2 rounded-xl bg-accent-500 px-6 py-3.5 text-base font-bold text-slate-900 shadow-lg hover:bg-accent-400 focus:outline-none focus-visible:ring-4 focus-visible:ring-accent-300">
                    Start Planning <x-icon name="arrow-right" class="size-5" />
                </a>
                <a href="{{ route('destinations.index') }}" class="inline-flex items-center rounded-xl border border-white/50 px-6 py-3.5 text-base font-semibold text-white hover:bg-white/10">Explore destinations</a>
            </div>
            <ul class="mt-10 flex flex-wrap gap-2" aria-label="Plan by interest">
                @foreach ($siteCategories as $category)
                    <li><a href="{{ route('plan', ['category' => $category->slug]) }}" class="inline-block rounded-full bg-white/10 px-4 py-2 text-sm font-medium text-white ring-1 ring-white/30 backdrop-blur hover:bg-white/20">{{ $category->name }}</a></li>
                @endforeach
            </ul>
            @if ($hero?->cover)
                <p class="mt-10 text-xs text-slate-300">{{ $hero->name }} · <x-photo-credit :media="$hero->cover" class="text-slate-300" /></p>
            @endif
        </div>
    </section>

    {{-- How it works --}}
    <section class="mx-auto max-w-7xl px-4 py-16 sm:px-6 lg:px-8" aria-labelledby="how-heading">
        <x-section-heading id="how-heading" eyebrow="How it works" title="Your trip in three steps" />
        <ol class="grid gap-6 md:grid-cols-3">
            @foreach ([
                ['Choose your style', 'Budget, Premium or Luxury, then the interests and districts you want to see.', 'sparkles'],
                ['Get a day-by-day plan', 'We arrange your places by route and drive time, with hotels, meals, vehicle and guide.', 'map'],
                ['An agent confirms it', 'Submit in one click. A local travel agent checks the plan and confirms the final price.', 'check'],
            ] as $i => [$heading, $text, $icon])
                <li class="rounded-2xl bg-slate-50 p-6 ring-1 ring-slate-200">
                    <span class="grid size-11 place-items-center rounded-full bg-primary-700 text-white"><x-icon :name="$icon" class="size-6" /></span>
                    <h3 class="mt-4 text-lg font-semibold">{{ $i + 1 }}. {{ $heading }}</h3>
                    <p class="mt-2 text-sm leading-6 text-slate-600">{{ $text }}</p>
                </li>
            @endforeach
        </ol>
    </section>

    {{-- Packages --}}
    @if ($packages->isNotEmpty() && Route::has('packages.show'))
        <section class="bg-slate-50 py-16" aria-labelledby="packages-heading">
            <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                <x-section-heading id="packages-heading" eyebrow="Ready-made" title="Suggested trip packages" subtitle="Book a proven route as it is, or open it in the Trip Builder and make it yours." />
                <div class="grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
                    @foreach ($packages as $package)
                        <x-package-card :package="$package" />
                    @endforeach
                </div>
            </div>
        </section>
    @endif

    {{-- Categories --}}
    <section class="mx-auto max-w-7xl px-4 py-16 sm:px-6 lg:px-8" aria-labelledby="categories-heading">
        <x-section-heading id="categories-heading" eyebrow="Interests" title="Explore by category" :link="route('destinations.index')" linkLabel="All destinations" />
        <div class="grid grid-cols-2 gap-4 lg:grid-cols-3">
            @foreach ($categoryTiles as $tile)
                <a href="{{ route('destinations.index', ['category' => $tile['category']->slug]) }}" class="group relative isolate flex aspect-[4/3] items-end overflow-hidden rounded-2xl bg-primary-800 p-5 text-white shadow-sm sm:aspect-[16/9]">
                    @if ($tile['cover'])
                        <x-media-img :media="$tile['cover']" sizes="(min-width: 1024px) 33vw, 50vw" alt="" class="absolute inset-0 -z-20 size-full object-cover transition duration-500 group-hover:scale-105" />
                    @endif
                    <span class="absolute inset-0 -z-10 bg-gradient-to-t from-slate-950/80 to-transparent"></span>
                    <span>
                        <span class="block text-lg font-bold sm:text-xl">{{ $tile['category']->name }}</span>
                        <span class="text-sm text-slate-200">{{ $tile['count'] }} {{ Str::plural('place', $tile['count']) }}</span>
                    </span>
                </a>
            @endforeach
        </div>
    </section>

    {{-- Top destinations --}}
    @if ($topPlaces->isNotEmpty())
        <section class="bg-slate-50 py-16" aria-labelledby="top-heading">
            <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                <x-section-heading id="top-heading" eyebrow="Favourites" title="Top destinations" :link="route('destinations.index')" />
                <div class="grid gap-6 sm:grid-cols-2 xl:grid-cols-4">
                    @foreach ($topPlaces as $place)
                        <x-place-card :place="$place" />
                    @endforeach
                </div>
            </div>
        </section>
    @endif

    {{-- Hidden gems --}}
    @if ($hiddenGems->isNotEmpty())
        <section class="mx-auto max-w-7xl px-4 py-16 sm:px-6 lg:px-8" aria-labelledby="gems-heading">
            <x-section-heading id="gems-heading" eyebrow="Off the beaten track" title="Hidden gems" subtitle="Quiet places most visitors miss." :link="route('destinations.index', ['gems' => 1])" />
            <div class="-mx-4 flex snap-x gap-4 overflow-x-auto px-4 pb-4">
                @foreach ($hiddenGems as $place)
                    <x-place-card :place="$place" class="w-72 shrink-0 snap-start" sizes="288px" />
                @endforeach
            </div>
        </section>
    @endif

    {{-- Why choose us --}}
    <section class="bg-primary-900 py-16 text-white" aria-labelledby="why-heading">
        <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
            <h2 id="why-heading" class="text-3xl font-bold tracking-tight text-white">Why travel with LankaGuide360</h2>
            <div class="mt-10 grid gap-8 sm:grid-cols-2 lg:grid-cols-4">
                @foreach ([
                    ['Plans in minutes', 'A full day-by-day route with drive times, not a vague quote.', 'map'],
                    ['Clear prices', 'Every hotel night, ticket and kilometre shown before you commit.', 'sparkles'],
                    ['Real local agents', 'People who know Sri Lanka check and confirm every plan.', 'user-group'],
                    ['Your trip, your way', 'Budget to luxury, beaches to hidden gems. Change anything.', 'star'],
                ] as [$heading, $text, $icon])
                    <div>
                        <x-icon :name="$icon" class="size-8 text-accent-400" />
                        <h3 class="mt-3 text-lg font-semibold text-white">{{ $heading }}</h3>
                        <p class="mt-1 text-sm text-primary-100">{{ $text }}</p>
                    </div>
                @endforeach
            </div>
        </div>
    </section>

    {{-- Testimonials --}}
    @if ($reviews->isNotEmpty())
        <section class="mx-auto max-w-4xl px-4 py-16 text-center sm:px-6" aria-labelledby="reviews-heading"
                 x-data="carousel({{ $reviews->count() }})" @mouseenter="pause()" @mouseleave="play()" @focusin="pause()">
            <h2 id="reviews-heading" class="text-3xl font-bold tracking-tight">What travellers say</h2>
            <div class="relative mt-8 min-h-48" aria-live="polite">
                @foreach ($reviews as $i => $review)
                    <figure x-show="index === {{ $i }}" @if ($i > 0) x-cloak @endif x-transition.opacity class="mx-auto max-w-2xl">
                        <p class="text-accent-500" aria-label="{{ $review->rating }} out of 5 stars">{{ str_repeat('★', $review->rating) }}</p>
                        <blockquote class="mt-3 text-lg leading-8 text-slate-700">“{{ $review->comment }}”</blockquote>
                        <figcaption class="mt-4 text-sm font-semibold text-slate-900">{{ $review->author_name }}@if ($review->country)<span class="font-normal text-slate-500"> · {{ $review->country }}</span>@endif</figcaption>
                    </figure>
                @endforeach
            </div>
            @if ($reviews->count() > 1)
                <div class="mt-6 flex items-center justify-center gap-3">
                    <button type="button" @click="prev()" class="rounded-full p-2 ring-1 ring-slate-300 hover:bg-slate-100"><span class="sr-only">Previous review</span><x-icon name="chevrons-left" class="size-4" /></button>
                    <button type="button" @click="next()" class="rounded-full p-2 ring-1 ring-slate-300 hover:bg-slate-100"><span class="sr-only">Next review</span><x-icon name="chevrons-left" class="size-4 rotate-180" /></button>
                </div>
            @endif
        </section>
    @endif

    {{-- Stats --}}
    <section class="border-y border-slate-200 bg-slate-50" aria-label="LankaGuide360 in numbers">
        <dl class="mx-auto grid max-w-7xl grid-cols-2 gap-8 px-4 py-12 text-center sm:px-6 lg:grid-cols-4 lg:px-8">
            @foreach ([['places', 'Places to visit'], ['districts', 'Districts covered'], ['hotels', 'Hotels & stays'], ['trips', 'Trips planned']] as [$key, $label])
                <div x-data="countUp({{ (int) $stats[$key] }})">
                    <dt class="text-sm text-slate-500">{{ $label }}</dt>
                    <dd class="mt-1 text-4xl font-extrabold text-primary-700"><span x-text="formatted">{{ number_format($stats[$key]) }}</span></dd>
                </div>
            @endforeach
        </dl>
    </section>

    {{-- Newsletter --}}
    <section class="mx-auto max-w-3xl px-4 py-16 text-center sm:px-6" aria-labelledby="newsletter-heading">
        <h2 id="newsletter-heading" class="text-2xl font-bold">Travel tips, once a month</h2>
        <p class="mt-2 text-slate-600">Best seasons, new hidden gems and practical advice. No spam.</p>
        <form method="POST" action="{{ route('newsletter.store') }}" class="mx-auto mt-6 flex max-w-md flex-col gap-2 sm:flex-row">
            @csrf
            <label for="home-newsletter" class="sr-only">Email address</label>
            <input id="home-newsletter" type="email" name="email" required autocomplete="email" placeholder="you@example.com" class="form-control flex-1">
            <button type="submit" class="btn btn-primary">Subscribe</button>
        </form>
        @if (session('newsletter'))
            <p class="mt-3 text-sm text-primary-700" role="status">{{ session('newsletter') }}</p>
        @endif
        @error('email')<p class="mt-3 text-sm text-red-600">{{ $message }}</p>@enderror
    </section>
</x-app-layout>
