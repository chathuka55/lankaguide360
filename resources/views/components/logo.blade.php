@props(['inverted' => false])

<a href="{{ route('home') }}" {{ $attributes->merge(['class' => 'inline-flex items-center gap-2 rounded-md focus:outline-none focus-visible:ring-2 focus-visible:ring-primary-500']) }}>
    <x-application-logo class="size-9" />
    <span class="text-lg font-extrabold tracking-tight {{ $inverted ? 'text-white' : 'text-slate-900' }}">
        LankaGuide<span class="{{ $inverted ? 'text-accent-400' : 'text-primary-700' }}">360</span>
    </span>
</a>
