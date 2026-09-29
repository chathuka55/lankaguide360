@php
$links = [
    ['label' => 'Home', 'route' => 'home'],
    ['label' => 'Destinations', 'route' => 'destinations.index', 'active' => ['destinations.*', 'districts.*']],
    ['label' => 'Packages', 'route' => 'packages.index', 'active' => ['packages.*']],
    ['label' => 'Plan a Trip', 'route' => 'plan'],
    ['label' => 'About', 'route' => 'about'],
    ['label' => 'Contact', 'route' => 'contact'],
];
@endphp

<header x-data="{ open: false }" @keydown.escape.window="open = false"
        class="sticky top-0 z-40 border-b border-slate-200/80 bg-white/95 backdrop-blur supports-[backdrop-filter]:bg-white/80">
    <nav class="mx-auto flex h-16 max-w-7xl items-center justify-between gap-4 px-4 sm:px-6 lg:px-8" aria-label="Main">
        <x-logo />

        {{-- Desktop links --}}
        <ul class="hidden items-center gap-1 lg:flex">
            @foreach ($links as $link)
                @php($isActive = request()->routeIs($link['active'] ?? $link['route']))
                <li>
                    <a href="{{ route($link['route']) }}" @if ($isActive) aria-current="page" @endif
                       class="rounded-lg px-3 py-2 text-sm font-medium transition {{ $isActive ? 'bg-primary-50 text-primary-800' : 'text-slate-600 hover:bg-slate-100 hover:text-slate-900' }}">
                        {{ $link['label'] }}
                    </a>
                </li>
            @endforeach
        </ul>

        <div class="hidden items-center gap-2 lg:flex">
            @auth
                <a href="{{ route('my-trips') }}"
                   class="rounded-lg px-3 py-2 text-sm font-medium {{ request()->routeIs('my-trips') ? 'bg-primary-50 text-primary-800' : 'text-slate-600 hover:bg-slate-100 hover:text-slate-900' }}">
                    My Trips
                </a>

                <x-dropdown align="right" width="w-56">
                    <x-slot name="trigger">
                        <button type="button" class="inline-flex items-center gap-2 rounded-full border border-slate-200 py-1 ps-1 pe-3 text-sm font-medium text-slate-700 hover:bg-slate-50 focus:outline-none focus-visible:ring-2 focus-visible:ring-primary-500">
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
                <a href="{{ route('login') }}" class="rounded-lg px-3 py-2 text-sm font-medium text-slate-600 hover:bg-slate-100 hover:text-slate-900">Log in</a>
                <a href="{{ route('register') }}" class="rounded-lg bg-primary-700 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-primary-800 focus:outline-none focus-visible:ring-2 focus-visible:ring-primary-500 focus-visible:ring-offset-2">Register</a>
            @endauth
        </div>

        {{-- Mobile toggle --}}
        <button type="button" @click="open = ! open" :aria-expanded="open.toString()" aria-controls="mobile-menu"
                class="inline-flex items-center justify-center rounded-lg p-2 text-slate-600 hover:bg-slate-100 focus:outline-none focus-visible:ring-2 focus-visible:ring-primary-500 lg:hidden">
            <span class="sr-only">Open main menu</span>
            <x-icon name="menu" class="size-6" x-show="! open" />
            <x-icon name="x" class="size-6" x-show="open" x-cloak />
        </button>
    </nav>

    {{-- Mobile menu --}}
    <div id="mobile-menu" x-show="open" x-cloak x-transition.origin.top
         class="border-t border-slate-200 bg-white lg:hidden">
        <ul class="space-y-1 px-4 py-3">
            @foreach ($links as $link)
                @php($isActive = request()->routeIs($link['active'] ?? $link['route']))
                <li>
                    <a href="{{ route($link['route']) }}" @if ($isActive) aria-current="page" @endif
                       class="block rounded-lg px-3 py-2.5 text-base font-medium {{ $isActive ? 'bg-primary-50 text-primary-800' : 'text-slate-700 hover:bg-slate-100' }}">
                        {{ $link['label'] }}
                    </a>
                </li>
            @endforeach
        </ul>

        <div class="border-t border-slate-200 px-4 py-3">
            @auth
                <p class="px-3 text-sm font-semibold text-slate-900">{{ Auth::user()->name }}</p>
                <p class="px-3 text-xs text-slate-500">{{ Auth::user()->email }}</p>
                <ul class="mt-2 space-y-1">
                    @can('staff')
                        <li><a href="{{ route('admin.dashboard') }}" class="block rounded-lg px-3 py-2.5 text-base font-medium text-slate-700 hover:bg-slate-100">Admin panel</a></li>
                    @endcan
                    <li><a href="{{ route('my-trips') }}" class="block rounded-lg px-3 py-2.5 text-base font-medium text-slate-700 hover:bg-slate-100">My Trips</a></li>
                    <li><a href="{{ route('profile.edit') }}" class="block rounded-lg px-3 py-2.5 text-base font-medium text-slate-700 hover:bg-slate-100">Profile</a></li>
                    <li>
                        <form method="POST" action="{{ route('logout') }}">
                            @csrf
                            <button type="submit" class="block w-full rounded-lg px-3 py-2.5 text-start text-base font-medium text-slate-700 hover:bg-slate-100">Log out</button>
                        </form>
                    </li>
                </ul>
            @else
                <div class="grid grid-cols-2 gap-2">
                    <a href="{{ route('login') }}" class="rounded-lg border border-slate-300 px-4 py-2.5 text-center text-sm font-semibold text-slate-700 hover:bg-slate-50">Log in</a>
                    <a href="{{ route('register') }}" class="rounded-lg bg-primary-700 px-4 py-2.5 text-center text-sm font-semibold text-white hover:bg-primary-800">Register</a>
                </div>
            @endauth
        </div>
    </div>
</header>
