{{-- Emblem + name. Light backgrounds: the logo's own green and blue; dark (inverted): white disc and white name. --}}
@props(['inverted' => false])

<a href="{{ route('home') }}" {{ $attributes->merge(['class' => 'inline-flex items-center gap-2.5 rounded-md focus:outline-none focus-visible:ring-2 focus-visible:ring-primary-500']) }}>
    <x-application-logo :badge="$inverted" class="size-11" />
    <span class="text-xl font-extrabold leading-none tracking-tight">
        @if ($inverted)
            <span class="text-white">LankaGuide</span><span class="text-accent-400">360</span>
        @else
            <span class="text-logo-green">Lanka</span><span class="text-logo-blue">Guide360</span>
        @endif
    </span>
</a>
