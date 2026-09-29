@props(['title', 'subtitle' => null, 'eyebrow' => null])

<section class="bg-gradient-to-br from-primary-800 via-primary-700 to-primary-600 text-white">
    <div class="mx-auto max-w-7xl px-4 py-14 sm:px-6 sm:py-20 lg:px-8">
        @if ($eyebrow)
            <p class="text-sm font-semibold uppercase tracking-wider text-accent-300">{{ $eyebrow }}</p>
        @endif
        <h1 class="mt-2 text-3xl font-extrabold tracking-tight text-white sm:text-5xl">{{ $title }}</h1>
        @if ($subtitle)
            <p class="mt-4 max-w-2xl text-base text-primary-50 sm:text-lg">{{ $subtitle }}</p>
        @endif
        {{ $slot }}
    </div>
</section>
