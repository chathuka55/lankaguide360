<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        @include('layouts.partials.head', ['title' => $title, 'description' => null])
    </head>
    <body class="flex min-h-screen flex-col font-sans">
        <x-navbar />

        <main id="main" class="flex flex-1 items-center justify-center bg-gradient-to-b from-primary-50 to-white px-4 py-12">
            <div class="w-full max-w-md rounded-2xl bg-white p-6 shadow-xl ring-1 ring-slate-200 sm:p-8">
                <a href="{{ route('home') }}" class="mx-auto mb-6 block w-40">
                    <img src="{{ asset('images/brand/logo-stacked.webp') }}" alt="LankaGuide360" width="800" height="768" class="h-auto w-full" decoding="async">
                </a>
                {{ $slot }}
            </div>
        </main>

        <x-footer />
        @livewireScriptConfig
    </body>
</html>
