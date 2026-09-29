{{--
    Responsive WebP image from a Media row (CLAUDE.md UI rules: alt, lazy, width/height, srcset).
    <x-media-img :media="$place->cover" sizes="(min-width: 1024px) 33vw, 100vw" class="…" />
--}}
@props(['media', 'width' => 800, 'sizes' => '100vw', 'eager' => false, 'alt' => null])

@php
    $ratio = $media->width && $media->height ? $media->height / $media->width : 0.66;
@endphp

<img src="{{ $media->url($width) }}" srcset="{{ $media->srcset() }}" sizes="{{ $sizes }}"
     alt="{{ $alt ?? $media->alt }}" width="{{ $width }}" height="{{ (int) round($width * $ratio) }}"
     loading="{{ $eager ? 'eager' : 'lazy' }}" decoding="async" @if ($eager) fetchpriority="high" @endif
     {{ $attributes }}>
