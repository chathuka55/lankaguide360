{{-- Home section heading: small eyebrow, bold title with an italic serif accent, optional link. --}}
@props(['title', 'accent' => null, 'eyebrow' => null, 'subtitle' => null, 'id' => null, 'link' => null, 'linkLabel' => 'See all', 'dark' => false])

<div {{ $attributes->class(['mb-10 flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between']) }} data-reveal>
    <div class="max-w-2xl">
        @if ($eyebrow)
            <p @class(['inline-flex items-center gap-2 text-xs font-semibold uppercase tracking-[0.2em]', 'text-accent-300' => $dark, 'text-primary-700' => ! $dark])>
                <span class="h-px w-6 bg-current"></span>{{ $eyebrow }}
            </p>
        @endif
        <h2 @if ($id) id="{{ $id }}" @endif @class(['mt-3 text-4xl font-bold leading-[1.05] tracking-tight sm:text-5xl', 'text-white' => $dark, 'text-primary-950' => ! $dark])>
            {{ $title }} @if ($accent) <span @class(['font-display font-normal italic', 'text-accent-300' => $dark, 'text-primary-700' => ! $dark])>{{ $accent }}</span> @endif
        </h2>
        @if ($subtitle)
            <p @class(['mt-4 text-lg', 'text-primary-100/80' => $dark, 'text-slate-600' => ! $dark])>{{ $subtitle }}</p>
        @endif
    </div>
    {{ $slot }}
    @if ($link)
        <a href="{{ $link }}" @class(['group inline-flex shrink-0 items-center gap-2 rounded-full py-1.5 ps-4 pe-1.5 text-sm font-semibold ring-1 transition', 'text-white ring-white/30 hover:bg-white/10' => $dark, 'text-primary-900 ring-primary-900/15 hover:bg-white' => ! $dark])>
            {{ $linkLabel }}
            <span @class(['grid size-7 place-items-center rounded-full transition group-hover:rotate-45', 'bg-white/15' => $dark, 'bg-primary-800 text-white' => ! $dark])><x-icon name="arrow-up-right" class="size-3.5" /></span>
        </a>
    @endif
</div>
