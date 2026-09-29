@php
/*
 * Sidebar menu. `route` null = section not built yet (shown disabled with the phase that adds it).
 * `can` hides the item from users who fail the gate; the routes themselves are also protected.
 */
$menu = [
    'Overview' => [
        ['label' => 'Dashboard', 'icon' => 'squares', 'route' => 'admin.dashboard', 'active' => 'admin.dashboard', 'can' => 'staff'],
        ['label' => 'Trip requests', 'icon' => 'inbox', 'route' => 'admin.trips.index', 'active' => 'admin.trips.*', 'can' => 'staff'],
        ['label' => 'Review queue', 'icon' => 'photo', 'route' => 'admin.review.index', 'active' => 'admin.review.*', 'can' => 'admin'],
    ],
    'Master data' => [
        ['label' => 'Places', 'icon' => 'map-pin', 'route' => 'admin.places.index', 'active' => 'admin.places.*', 'can' => 'staff'],
        ['label' => 'Districts', 'icon' => 'map', 'route' => 'admin.districts.index', 'active' => 'admin.districts.*', 'can' => 'staff'],
        ['label' => 'Categories', 'icon' => 'sparkles', 'route' => 'admin.categories.index', 'active' => 'admin.categories.*', 'can' => 'staff'],
        ['label' => 'Hotels', 'icon' => 'building', 'route' => 'admin.hotels.index', 'active' => 'admin.hotels.*', 'can' => 'staff'],
        ['label' => 'Vehicles', 'icon' => 'truck', 'route' => 'admin.vehicles.index', 'active' => 'admin.vehicles.*', 'can' => 'staff'],
        ['label' => 'Guides', 'icon' => 'user-group', 'route' => 'admin.guides.index', 'active' => 'admin.guides.*', 'can' => 'staff'],
        ['label' => 'Cuisines', 'icon' => 'check', 'route' => 'admin.cuisines.index', 'active' => 'admin.cuisines.*', 'can' => 'staff'],
        ['label' => 'Packages', 'icon' => 'gift', 'route' => 'admin.packages.index', 'active' => 'admin.packages.*', 'can' => 'staff'],
    ],
    'Customers' => [
        ['label' => 'Reviews', 'icon' => 'star', 'route' => 'admin.reviews.index', 'active' => 'admin.reviews.*', 'can' => 'admin'],
        ['label' => 'Contact messages', 'icon' => 'envelope', 'route' => 'admin.messages.index', 'active' => 'admin.messages.*', 'can' => 'admin'],
        ['label' => 'Chatbot conversations', 'icon' => 'chat', 'route' => 'admin.chat-logs.index', 'active' => 'admin.chat-logs.*', 'can' => 'admin'],
        ['label' => 'FAQs', 'icon' => 'sparkles', 'route' => 'admin.faqs.index', 'active' => 'admin.faqs.*', 'can' => 'admin'],
    ],
    'System' => [
        ['label' => 'Users', 'icon' => 'users', 'route' => 'admin.users.index', 'active' => 'admin.users.*', 'can' => 'admin'],
        ['label' => 'Import tools', 'icon' => 'download', 'route' => 'admin.imports.index', 'active' => 'admin.imports.*', 'can' => 'admin'],
        ['label' => 'Settings', 'icon' => 'cog', 'route' => 'admin.settings.edit', 'active' => 'admin.settings.*', 'can' => 'admin'],
    ],
];
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        @include('layouts.partials.head', ['title' => ($title ? $title.' · Admin' : 'Admin'), 'description' => null])
        <meta name="robots" content="noindex, nofollow">
        @vite(['resources/js/admin.js'])
    </head>
    <body class="bg-slate-100 font-sans"
          x-data="{
              mobileOpen: false,
              collapsed: (() => { try { return localStorage.getItem('lg-admin-collapsed') === '1' } catch (e) { return false } })(),
              toggleCollapsed() {
                  this.collapsed = ! this.collapsed;
                  try { localStorage.setItem('lg-admin-collapsed', this.collapsed ? '1' : '0') } catch (e) {}
              },
          }"
          @keydown.escape.window="mobileOpen = false">

        {{-- Mobile backdrop --}}
        <div x-show="mobileOpen" x-cloak x-transition.opacity @click="mobileOpen = false" class="fixed inset-0 z-30 bg-slate-900/50 lg:hidden"></div>

        {{-- Sidebar --}}
        <aside id="admin-sidebar"
               class="fixed inset-y-0 start-0 z-40 flex w-64 -translate-x-full flex-col bg-slate-900 text-slate-300 transition-all duration-200 lg:translate-x-0"
               :class="{ 'translate-x-0!': mobileOpen, 'lg:w-20!': collapsed }"
               aria-label="Admin navigation">
            <div class="flex h-16 shrink-0 items-center justify-between gap-2 border-b border-slate-800 px-4">
                <a href="{{ route('admin.dashboard') }}" class="flex items-center gap-2 overflow-hidden">
                    <x-application-logo class="size-9 shrink-0" />
                    <span class="whitespace-nowrap text-base font-extrabold text-white" :class="{ 'lg:hidden': collapsed }">
                        LankaGuide<span class="text-accent-400">360</span>
                    </span>
                </a>
                <button type="button" @click="mobileOpen = false" class="rounded-md p-1 text-slate-400 hover:text-white lg:hidden">
                    <span class="sr-only">Close sidebar</span>
                    <x-icon name="x" class="size-6" />
                </button>
            </div>

            <nav class="flex-1 space-y-6 overflow-y-auto px-3 py-5">
                @foreach ($menu as $group => $items)
                    @php($visible = collect($items)->filter(fn ($item) => Gate::allows($item['can'])))
                    @continue($visible->isEmpty())
                    <div>
                        <p class="mb-2 px-3 text-xs font-semibold uppercase tracking-wider text-slate-500" :class="{ 'lg:sr-only': collapsed }">{{ $group }}</p>
                        <ul class="space-y-1">
                            @foreach ($visible as $item)
                                <li>
                                    @if ($item['route'])
                                        @php($active = request()->routeIs($item['active'] ?? $item['route']))
                                        <a href="{{ route($item['route']) }}" title="{{ $item['label'] }}" @if ($active) aria-current="page" @endif
                                           class="flex items-center gap-3 rounded-lg px-3 py-2 text-sm font-medium transition {{ $active ? 'bg-primary-700 text-white' : 'hover:bg-slate-800 hover:text-white' }}">
                                            <x-icon :name="$item['icon']" class="size-5" />
                                            <span class="whitespace-nowrap" :class="{ 'lg:hidden': collapsed }">{{ $item['label'] }}</span>
                                        </a>
                                    @else
                                        <span aria-disabled="true" title="{{ $item['label'] }} (coming in phase {{ $item['phase'] }})"
                                              class="flex cursor-not-allowed items-center gap-3 rounded-lg px-3 py-2 text-sm font-medium text-slate-500">
                                            <x-icon :name="$item['icon']" class="size-5" />
                                            <span class="flex-1 whitespace-nowrap" :class="{ 'lg:hidden': collapsed }">{{ $item['label'] }}</span>
                                            <span class="rounded bg-slate-800 px-1.5 py-0.5 text-[10px] font-semibold uppercase text-slate-400" :class="{ 'lg:hidden': collapsed }">Soon</span>
                                        </span>
                                    @endif
                                </li>
                            @endforeach
                        </ul>
                    </div>
                @endforeach
            </nav>

            <div class="hidden border-t border-slate-800 p-3 lg:block">
                <button type="button" @click="toggleCollapsed()" :aria-expanded="(! collapsed).toString()" aria-controls="admin-sidebar"
                        class="flex w-full items-center gap-3 rounded-lg px-3 py-2 text-sm font-medium text-slate-400 hover:bg-slate-800 hover:text-white">
                    <x-icon name="chevrons-left" class="size-5 transition-transform" x-bind:class="{ 'rotate-180': collapsed }" />
                    <span :class="{ 'lg:hidden': collapsed }">Collapse</span>
                </button>
            </div>
        </aside>

        {{-- Main column --}}
        <div class="flex min-h-screen flex-col transition-all duration-200 lg:ps-64" :class="{ 'lg:ps-20!': collapsed }">
            <header class="sticky top-0 z-20 flex h-16 items-center gap-3 border-b border-slate-200 bg-white px-4 sm:px-6">
                <button type="button" @click="mobileOpen = true" :aria-expanded="mobileOpen.toString()" aria-controls="admin-sidebar"
                        class="rounded-lg p-2 text-slate-600 hover:bg-slate-100 lg:hidden">
                    <span class="sr-only">Open sidebar</span>
                    <x-icon name="menu" class="size-6" />
                </button>

                <h1 class="min-w-0 flex-1 truncate text-lg font-semibold text-slate-900">{{ $title ?? 'Admin' }}</h1>

                <a href="{{ route('home') }}" target="_blank" class="hidden items-center gap-1.5 rounded-lg px-3 py-2 text-sm font-medium text-slate-600 hover:bg-slate-100 sm:inline-flex">
                    View site <x-icon name="external" class="size-4" />
                </a>

                <x-dropdown align="right" width="w-56">
                    <x-slot name="trigger">
                        <button type="button" class="inline-flex items-center gap-2 rounded-full border border-slate-200 py-1 ps-1 pe-2 text-sm font-medium text-slate-700 hover:bg-slate-50 sm:pe-3">
                            <span class="grid size-8 place-items-center rounded-full bg-primary-700 text-xs font-bold text-white">
                                {{ str(Auth::user()->name)->substr(0, 1)->upper() }}
                            </span>
                            <span class="hidden max-w-32 truncate sm:inline">{{ Auth::user()->name }}</span>
                            <x-icon name="chevron-down" class="size-4 text-slate-400" />
                        </button>
                    </x-slot>
                    <x-slot name="content">
                        <div class="border-b border-slate-100 px-4 py-2 text-xs text-slate-500">
                            {{ Auth::user()->email }}<br>
                            <span class="font-medium text-slate-700">{{ Auth::user()->role->label() }}</span>
                        </div>
                        <x-dropdown-link :href="route('home')">View site</x-dropdown-link>
                        <x-dropdown-link :href="route('profile.edit')">Profile</x-dropdown-link>
                        <form method="POST" action="{{ route('logout') }}">
                            @csrf
                            <x-dropdown-link :href="route('logout')" onclick="event.preventDefault(); this.closest('form').submit();">Log out</x-dropdown-link>
                        </form>
                    </x-slot>
                </x-dropdown>
            </header>

            <main id="main" class="flex-1 p-4 sm:p-6 lg:p-8">
                <x-admin.flash />

                {{ $slot }}
            </main>
        </div>

        @stack('scripts')
        @livewireScriptConfig
    </body>
</html>
