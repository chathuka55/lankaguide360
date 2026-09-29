{{-- "Photo: Author, License, via Wikimedia Commons" with links (CLAUDE.md image rules). --}}
@props(['media'])

@if ($media->author || $media->license)
    <span {{ $attributes->class(['text-xs']) }}>
        Photo:
        @if ($media->source_url)
            <a href="{{ $media->source_url }}" target="_blank" rel="noopener" class="underline decoration-dotted hover:decoration-solid">{{ $media->author ?: 'source' }}</a>@else{{ $media->author }}@endif{{ $media->license ? ',' : '' }}
        @if ($media->license)
            @if ($media->license_url)
                <a href="{{ $media->license_url }}" target="_blank" rel="noopener license" class="underline decoration-dotted hover:decoration-solid">{{ $media->license }}</a>
            @else
                {{ $media->license }}
            @endif
        @endif
        @if ($media->source?->value === 'commons'), via Wikimedia Commons @endif
    </span>
@endif
