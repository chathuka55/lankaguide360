{{-- Destination card: photo, name, district, categories, time needed. --}}
@props(['place', 'sizes' => '(min-width: 1280px) 25vw, (min-width: 640px) 50vw, 100vw'])

<article {{ $attributes->class(['group relative flex flex-col overflow-hidden rounded-2xl bg-white shadow-sm ring-1 ring-slate-200 transition hover:-translate-y-0.5 hover:shadow-lg']) }}>
    <div class="relative aspect-[4/3] overflow-hidden bg-slate-100">
        @if ($place->cover)
            <x-media-img :media="$place->cover" :sizes="$sizes" :alt="$place->name" class="size-full object-cover transition duration-500 group-hover:scale-105" />
        @else
            <div class="grid size-full place-items-center bg-gradient-to-br from-primary-100 to-primary-200 text-primary-700">
                <x-icon name="map-pin" class="size-10" />
            </div>
        @endif
        @if ($place->is_hidden_gem)
            <span class="absolute start-3 top-3 rounded-full bg-accent-500 px-2.5 py-1 text-xs font-bold text-slate-900 shadow">Hidden gem</span>
        @endif
    </div>
    <div class="flex flex-1 flex-col gap-2 p-4">
        <h3 class="text-base font-semibold leading-snug">
            <a href="{{ $place->url() }}" class="after:absolute after:inset-0 focus:outline-none">{{ $place->name }}</a>
        </h3>
        <p class="flex items-center gap-1 text-sm text-slate-500"><x-icon name="map-pin" class="size-4" /> {{ $place->district->name }}</p>
        @if ($place->short_description)
            <p class="line-clamp-2 text-sm text-slate-600">{{ $place->short_description }}</p>
        @endif
        <div class="mt-auto flex flex-wrap items-center gap-1.5 pt-2 text-xs">
            @foreach ($place->categories->take(2) as $category)
                <span class="rounded-full bg-primary-50 px-2 py-0.5 font-medium text-primary-800">{{ $category->name }}</span>
            @endforeach
            <span class="ms-auto text-slate-500">{{ $place->visitDuration() }}</span>
        </div>
    </div>
</article>
