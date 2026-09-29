@props(['title', 'eyebrow' => null, 'subtitle' => null, 'id' => null, 'link' => null, 'linkLabel' => 'See all'])

<div class="mb-8 flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
    <div class="max-w-2xl">
        @if ($eyebrow)
            <p class="text-sm font-semibold uppercase tracking-wider text-primary-700">{{ $eyebrow }}</p>
        @endif
        <h2 @if ($id) id="{{ $id }}" @endif class="mt-1 text-3xl font-bold tracking-tight">{{ $title }}</h2>
        @if ($subtitle)
            <p class="mt-2 text-slate-600">{{ $subtitle }}</p>
        @endif
    </div>
    @if ($link)
        <a href="{{ $link }}" class="inline-flex shrink-0 items-center gap-1 font-semibold text-primary-700 hover:underline">{{ $linkLabel }} <x-icon name="arrow-right" class="size-4" /></a>
    @endif
</div>
