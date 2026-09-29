<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="scroll-smooth">
    <head>
        @include('layouts.partials.head', ['title' => $title, 'description' => $description, 'image' => $image, 'ogType' => $ogType])
    </head>
    <body class="flex min-h-screen flex-col font-sans">
        <a href="#main" class="sr-only focus:not-sr-only focus:absolute focus:start-4 focus:top-4 focus:z-50 focus:rounded-lg focus:bg-white focus:px-4 focus:py-2 focus:shadow">Skip to content</a>

        <x-navbar />

        @isset($header)
            <div class="border-b border-slate-200 bg-slate-50">
                <div class="mx-auto max-w-7xl px-4 py-6 sm:px-6 lg:px-8">
                    {{ $header }}
                </div>
            </div>
        @endisset

        <main id="main" class="flex-1">
            {{ $slot }}
        </main>

        <x-footer />
        <x-chat-widget />

        {{-- Toasts (e.g. "Added to your trip") --}}
        <div x-data="toaster(@js(session('toast')))" class="pointer-events-none fixed inset-x-0 top-20 z-50 flex flex-col items-center gap-2 px-4" aria-live="polite">
            <template x-for="item in items" :key="item.id">
                <div x-transition class="pointer-events-auto rounded-xl bg-slate-900 px-4 py-3 text-sm font-medium text-white shadow-lg" x-text="item.message"></div>
            </template>
        </div>

        @stack('scripts')
        @livewireScriptConfig
    </body>
</html>
