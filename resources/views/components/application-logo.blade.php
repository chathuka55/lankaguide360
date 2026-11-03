{{-- LankaGuide360 emblem. Files in public/images/brand are made by scripts/brand-assets.php from
     resources/brand/lankaguide360-logo-source.png. On dark backgrounds use :badge="true" (white disc). --}}
@props(['badge' => false])

<img src="{{ asset($badge ? 'images/brand/mark-badge.webp' : 'images/brand/mark-128.png') }}" alt="" width="128" height="128" decoding="async"
     {{ $attributes->merge(['class' => 'shrink-0 object-contain']) }}>
