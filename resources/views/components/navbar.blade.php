@php
$planLinks = [
    ['label' => 'Trip Builder', 'hint' => 'Your own day-by-day plan in 8 easy steps', 'route' => 'plan', 'icon' => 'sparkles'],
    ['label' => 'Ready-made packages', 'hint' => 'Proven routes you can book or change', 'route' => 'packages.index', 'icon' => 'gift'],
    ['label' => 'Vehicle hire', 'hint' => 'With a driver, or self-drive for residents', 'route' => 'vehicles.index', 'icon' => 'truck'],
    ['label' => 'My Trips', 'hint' => 'Requests, e-receipts and messages', 'route' => 'my-trips', 'icon' => 'receipt'],
];
$destinationsActive = request()->routeIs('destinations.*', 'districts.*', 'hotels.*');
$planActive = request()->routeIs('plan', 'packages.*', 'vehicles.*', 'my-trips');
$plainLinks = [['label' => 'About', 'route' => 'about'], ['label' => 'Contact', 'route' => 'contact']];
$trigger = fn (bool $active) => 'inline-flex items-center gap-1 rounded-full px-3.5 py-2 text-sm font-medium transition focus:outline-none focus-visible:ring-2 focus-visible:ring-primary-500 '
    .($active ? 'bg-primary-700/10 text-primary-900' : 'text-slate-700 hover:bg-sand-200/70 hover:text-slate-900');
@endphp

<header x-data="{ open: false, menu: null, timer: null,
                  show(name) { clearTimeout(this.timer); this.menu = name },
                  hide() { this.timer = setTimeout(() => this.menu = null, 120) } }"
        @keydown.escape.window="menu = null; open = false"
        class="sticky top-0 z-40 border-b border-sand-200/80 bg-sand-50/90 backdrop-blur-md supports-[backdrop-filter]:bg-sand-50/75">
    <nav class="mx-auto flex h-16 max-w-7xl items-center justify-between gap-4 px-4 sm:px-6 lg:px-8" aria-label="Main">
        <x-logo />

        {{-- Desktop links --}}
        <ul class="hidden h-full items-center gap-1 lg:flex">
            {{-- Destinations mega menu --}}
            <li class="flex h-full items-center" @mouseenter="show('destinations')" @mouseleave="hide()">
                <button type="button" class="{{ $trigger($destinationsActive) }}" @click="menu = menu === 'destinations' ? null : 'destinations'"
                        :aria-expanded="(menu === 'destinations').toString()" aria-controls="menu-destinations">
                    Destinations <x-icon name="chevron-down" class="size-4 transition" ::class="menu === 'destinations' && 'rotate-180'" />
                </button>
                <div id="menu-destinations" x-show="menu === 'destinations'" x-cloak @click.outside="menu === 'destinations' && (menu = null)"
                     x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0 -translate-y-2" x-transition:enter-end="opacity-100 translate-y-0"
                     x-transition:leave="transition ease-in duration-100" x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0"
                     class="absolute inset-x-0 top-full border-b border-sand-200 bg-white shadow-xl shadow-primary-950/5">
                    <div class="mx-auto grid max-w-7xl grid-cols-12 gap-8 px-8 py-8">
                        <div class="col-span-5">
                            <p class="text-xs font-semibold uppercase tracking-wider text-slate-500">By interest</p>
                            <ul class="mt-3 grid grid-cols-2 gap-1">
                                @foreach ($siteCategories as $category)
                                    <li>
                                        <a href="{{ route('destinations.index', ['category' => $category->slug]) }}" class="group flex items-center gap-3 rounded-xl p-2.5 hover:bg-sand-100">
                                            <span class="grid size-10 shrink-0 place-items-center rounded-xl bg-primary-50 text-primary-700 transition group-hover:bg-primary-700 group-hover:text-white">
                                                <x-icon :name="$category->icon ?: 'map-pin'" class="size-5" />
                                            </span>
                                            <span class="text-sm font-medium text-slate-800">{{ $category->name }}</span>
                                        </a>
                                    </li>
                                @endforeach
                            </ul>
                        </div>
                        <div class="col-span-4">
                            <p class="text-xs font-semibold uppercase tracking-wider text-slate-500">By province</p>
                            <ul class="mt-3 grid grid-cols-2 gap-x-4 gap-y-1">
                                @foreach ($siteProvinces as $province)
                                    <li>
                                        <a href="{{ route('destinations.index', ['province' => $province->slug]) }}" class="flex items-baseline justify-between gap-2 rounded-lg px-2 py-1.5 text-sm text-slate-700 hover:bg-sand-100 hover:text-slate-900">
                                            {{ Str::before($province->name, ' Province') }}
                                            <span class="text-xs text-slate-400">{{ $province->districts->count() }}</span>
                                        </a>
                                    </li>
                                @endforeach
                            </ul>
                            <a href="{{ route('destinations.index', ['view' => 'map']) }}" class="mt-4 inline-flex items-center gap-1.5 text-sm font-semibold text-primary-700 hover:underline">
                                <x-icon name="map" class="size-4" /> Explore everything on the map
                            </a>
                        </div>
                        <a href="{{ route('destinations.index', ['gems' => 1]) }}" class="group relative col-span-3 flex flex-col justify-end overflow-hidden rounded-2xl bg-primary-800 p-5 text-white">
                            <span class="absolute -end-8 -top-8 size-32 rounded-full bg-accent-500/30 blur-2xl transition group-hover:scale-125"></span>
                            <x-icon name="gem" class="size-8 text-accent-300" />
                            <span class="mt-6 font-display text-2xl italic">Hidden gems</span>
                            <span class="mt-1 text-sm text-primary-100">Quiet places most visitors miss.</span>
                            <span class="mt-4 inline-flex items-center gap-1 text-sm font-semibold text-accent-300">Discover them <x-icon name="arrow-right" class="size-4 transition group-hover:translate-x-1" /></span>
                        </a>
                    </div>
                </div>
            </li>

            {{-- Plan your trip dropdown --}}
            <li class="relative flex h-full items-center" @mouseenter="show('plan')" @mouseleave="hide()">
                <button type="button" class="{{ $trigger($planActive) }}" @click="menu = menu === 'plan' ? null : 'plan'"
                        :aria-expanded="(menu === 'plan').toString()" aria-controls="menu-plan">
                    Plan your trip <x-icon name="chevron-down" class="size-4 transition" ::class="menu === 'plan' && 'rotate-180'" />
                </button>
                <div id="menu-plan" x-show="menu === 'plan'" x-cloak @click.outside="menu === 'plan' && (menu = null)"
                     x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0 -translate-y-2" x-transition:enter-end="opacity-100 translate-y-0"
                     x-transition:leave="transition ease-in duration-100" x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0"
                     class="absolute start-1/2 top-full w-96 -translate-x-1/2 rounded-2xl border border-sand-200 bg-white p-2 shadow-xl shadow-primary-950/10">
                    <ul>
                        @foreach ($planLinks as $link)
                            <li>
                                <a href="{{ route($link['route']) }}" @if (request()->routeIs($link['route'])) aria-current="page" @endif class="group flex items-start gap-3 rounded-xl p-3 hover:bg-sand-100">
                                    <span class="grid size-10 shrink-0 place-items-center rounded-xl bg-primary-50 text-primary-700 transition group-hover:bg-primary-700 group-hover:text-white">
                                        <x-icon :name="$link['icon']" class="size-5" />
                                    </span>
                                    <span>
                                        <span class="block text-sm font-semibold text-slate-900">{{ $link['label'] }}</span>
                                        <span class="block text-xs text-slate-500">{{ $link['hint'] }}</span>
                                    </span>
                                </a>
                            </li>
                        @endforeach
                    </ul>
                </div>
            </li>

            @foreach ($plainLinks as $link)
                @php($isActive = request()->routeIs($link['route']))
                <li>
                    <a href="{{ route($link['route']) }}" @if ($isActive) aria-current="page" @endif class="{{ $trigger($isActive) }}">{{ $link['label'] }}</a>
                </li>
            @endforeach
        </ul>

        <div class="hidden items-center gap-2 lg:flex">
            @auth
                <x-dropdown align="right" width="w-56">
                    <x-slot name="trigger">
                        <button type="button" class="inline-flex items-center gap-2 rounded-full border border-sand-200 bg-white py-1 ps-1 pe-3 text-sm font-medium text-slate-700 hover:bg-sand-100 focus:outline-none focus-visible:ring-2 focus-visible:ring-primary-500">
                            <span class="grid size-8 place-items-center rounded-full bg-primary-700 text-xs font-bold text-white">
                                {{ str(Auth::user()->name)->substr(0, 1)->upper() }}
                            </span>
                            <span class="max-w-32 truncate">{{ Auth::user()->name }}</span>
                            <x-icon name="chevron-down" class="size-4 text-slate-400" />
                        </button>
                    </x-slot>

                    <x-slot name="content">
                        <div class="border-b border-slate-100 px-4 py-2 text-xs text-slate-500">
                            Signed in as <span class="font-medium text-slate-700">{{ Auth::user()->role->label() }}</span>
                        </div>
                        @can('staff')
                            <x-dropdown-link :href="route('admin.dashboard')">Admin panel</x-dropdown-link>
                        @endcan
                        <x-dropdown-link :href="route('my-trips')">My Trips</x-dropdown-link>
                        <x-dropdown-link :href="route('profile.edit')">Profile</x-dropdown-link>
                        <form method="POST" action="{{ route('logout') }}">
                            @csrf
                            <x-dropdown-link :href="route('logout')" onclick="event.preventDefault(); this.closest('form').submit();">
                                Log out
                            </x-dropdown-link>
                        </form>
                    </x-slot>
                </x-dropdown>
            @else
                <a href="{{ route('login') }}" class="rounded-full px-3.5 py-2 text-sm font-medium text-slate-700 hover:bg-sand-200/70 hover:text-slate-900">Log in</a>
            @endauth
            <a href="{{ route('plan') }}" class="group inline-flex items-center gap-2 rounded-full bg-primary-800 py-1.5 ps-4 pe-1.5 text-sm font-semibold text-white shadow-sm transition hover:bg-primary-900 focus:outline-none focus-visible:ring-2 focus-visible:ring-primary-500 focus-visible:ring-offset-2">
                Plan a trip
                <span class="grid size-7 place-items-center rounded-full bg-accent-500 text-primary-950 transition group-hover:rotate-45"><x-icon name="arrow-up-right" class="size-4" /></span>
            </a>
        </div>

        {{-- Mobile toggle --}}
        <button type="button" @click="open = ! open" :aria-expanded="open.toString()" aria-controls="mobile-menu"
                class="inline-flex items-center justify-center rounded-lg p-2 text-slate-700 hover:bg-sand-200/70 focus:outline-none focus-visible:ring-2 focus-visible:ring-primary-500 lg:hidden">
            <span class="sr-only">Open main menu</span>
            <x-icon name="menu" class="size-6" x-show="! open" />
            <x-icon name="x" class="size-6" x-show="open" x-cloak />
        </button>
    </nav>

    {{-- Mobile menu --}}
    <div id="mobile-menu" x-show="open" x-cloak x-transition.origin.top data-lenis-prevent
         class="max-h-[calc(100dvh-4rem)] overflow-y-auto border-t border-sand-200 bg-sand-50 lg:hidden">
        <ul class="space-y-1 px-4 py-3">
            <li x-data="{ sub: false }">
                <button type="button" @click="sub = ! sub" :aria-expanded="sub.toString()" class="flex w-full items-center justify-between rounded-lg px-3 py-2.5 text-base font-medium text-slate-800 hover:bg-sand-200/70">
                    Destinations <x-icon name="chevron-down" class="size-5 transition" ::class="sub && 'rotate-180'" />
                </button>
                <div x-show="sub" x-collapse x-cloak class="ps-3">
                    <a href="{{ route('destinations.index') }}" class="block rounded-lg px-3 py-2 text-sm font-semibold text-primary-700">All destinations</a>
                    @foreach ($siteCategories as $category)
                        <a href="{{ route('destinations.index', ['category' => $category->slug]) }}" class="flex items-center gap-2 rounded-lg px-3 py-2 text-sm text-slate-700 hover:bg-sand-200/70"><x-icon :name="$category->icon ?: 'map-pin'" class="size-4 text-primary-700" /> {{ $category->name }}</a>
                    @endforeach
                    <a href="{{ route('destinations.index', ['gems' => 1]) }}" class="flex items-center gap-2 rounded-lg px-3 py-2 text-sm text-slate-700 hover:bg-sand-200/70"><x-icon name="gem" class="size-4 text-accent-600" /> Hidden gems</a>
                </div>
            </li>
            <li x-data="{ sub: {{ $planActive ? 'true' : 'false' }} }">
                <button type="button" @click="sub = ! sub" :aria-expanded="sub.toString()" class="flex w-full items-center justify-between rounded-lg px-3 py-2.5 text-base font-medium text-slate-800 hover:bg-sand-200/70">
                    Plan your trip <x-icon name="chevron-down" class="size-5 transition" ::class="sub && 'rotate-180'" />
                </button>
                <div x-show="sub" x-collapse x-cloak class="ps-3">
                    @foreach ($planLinks as $link)
                        <a href="{{ route($link['route']) }}" class="flex items-center gap-2 rounded-lg px-3 py-2 text-sm text-slate-700 hover:bg-sand-200/70"><x-icon :name="$link['icon']" class="size-4 text-primary-700" /> {{ $link['label'] }}</a>
                    @endforeach
                </div>
            </li>
            @foreach ([['label' => 'Home', 'route' => 'home'], ...$plainLinks] as $link)
                @php($isActive = request()->routeIs($link['route']))
                <li>
                    <a href="{{ route($link['route']) }}" @if ($isActive) aria-current="page" @endif
                       class="block rounded-lg px-3 py-2.5 text-base font-medium {{ $isActive ? 'bg-primary-700/10 text-primary-900' : 'text-slate-800 hover:bg-sand-200/70' }}">
                        {{ $link['label'] }}
                    </a>
                </li>
            @endforeach
        </ul>

        <div class="border-t border-sand-200 px-4 py-3">
            @auth
                <p class="px-3 text-sm font-semibold text-slate-900">{{ Auth::user()->name }}</p>
                <p class="px-3 text-xs text-slate-500">{{ Auth::user()->email }}</p>
                <ul class="mt-2 space-y-1">
                    @can('staff')
                        <li><a href="{{ route('admin.dashboard') }}" class="block rounded-lg px-3 py-2.5 text-base font-medium text-slate-700 hover:bg-sand-200/70">Admin panel</a></li>
                    @endcan
                    <li><a href="{{ route('my-trips') }}" class="block rounded-lg px-3 py-2.5 text-base font-medium text-slate-700 hover:bg-sand-200/70">My Trips</a></li>
                    <li><a href="{{ route('profile.edit') }}" class="block rounded-lg px-3 py-2.5 text-base font-medium text-slate-700 hover:bg-sand-200/70">Profile</a></li>
                    <li>
                        <form method="POST" action="{{ route('logout') }}">
                            @csrf
                            <button type="submit" class="block w-full rounded-lg px-3 py-2.5 text-start text-base font-medium text-slate-700 hover:bg-sand-200/70">Log out</button>
                        </form>
                    </li>
                </ul>
            @else
                <div class="grid grid-cols-2 gap-2">
                    <a href="{{ route('login') }}" class="rounded-full border border-sand-300 bg-white px-4 py-2.5 text-center text-sm font-semibold text-slate-700 hover:bg-sand-100">Log in</a>
                    <a href="{{ route('register') }}" class="rounded-full bg-primary-800 px-4 py-2.5 text-center text-sm font-semibold text-white hover:bg-primary-900">Register</a>
                </div>
            @endauth
            <a href="{{ route('plan') }}" class="mt-3 flex items-center justify-center gap-2 rounded-full bg-accent-500 px-4 py-3 text-sm font-bold text-primary-950 hover:bg-accent-400">Plan a trip <x-icon name="arrow-right" class="size-4" /></a>
        </div>
    </div>
</header>
