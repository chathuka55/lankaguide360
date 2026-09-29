{{-- Home page package card (SRS 3.3): image, name, days, tier badge, from-price, Book Now + Customize. --}}
@props(['package'])

@php($cover = $package->cover)

<article class="flex flex-col overflow-hidden rounded-2xl bg-white shadow-sm ring-1 ring-slate-200">
    <div class="relative aspect-[16/10] bg-primary-100">
        @if ($cover)
            <x-media-img :media="$cover" sizes="(min-width: 1024px) 33vw, (min-width: 640px) 50vw, 100vw" :alt="$package->name" class="size-full object-cover" />
        @elseif ($package->cover_image)
            <img src="{{ Str::startsWith($package->cover_image, ['http://', 'https://', '/']) ? $package->cover_image : Storage::disk('public')->url($package->cover_image) }}" alt="{{ $package->name }}" loading="lazy" width="800" height="500" class="size-full object-cover">
        @endif
        @if ($package->tier)
            <span class="absolute start-3 top-3 rounded-full bg-white/95 px-2.5 py-1 text-xs font-bold text-primary-800 shadow">{{ $package->tier->label() }}</span>
        @endif
    </div>
    <div class="flex flex-1 flex-col gap-2 p-5">
        <h3 class="text-lg font-semibold">{{ $package->name }}</h3>
        @if ($package->summary)
            <p class="text-sm text-slate-600">{{ $package->summary }}</p>
        @endif
        <p class="mt-auto pt-2 text-sm text-slate-500">
            @if ($package->days){{ $package->days }} days · @endif
            @if ($package->from_price) from <span class="text-lg font-bold text-slate-900">${{ number_format((float) $package->from_price) }}</span> per person @endif
        </p>
        <div class="mt-3 flex gap-2">
            <a href="{{ route('packages.show', $package) }}" class="btn btn-primary flex-1">Book Now</a>
            @if (Route::has('packages.customize'))
                <form method="POST" action="{{ route('packages.customize', $package) }}" class="flex-1">
                    @csrf
                    <button type="submit" class="btn btn-secondary w-full">Customize</button>
                </form>
            @endif
        </div>
    </div>
</article>
