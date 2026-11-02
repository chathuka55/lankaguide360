{{-- LankaGuide360 mark: the island of Sri Lanka inside a 360° orbit with a rising sun, on brand teal.
     The same artwork is public/favicon.svg. --}}
@php($gid = 'lg-grad-'.substr(md5(uniqid('', true)), 0, 6))
<svg viewBox="0 0 40 40" xmlns="http://www.w3.org/2000/svg" aria-hidden="true" {{ $attributes }}>
    <defs>
        <linearGradient id="{{ $gid }}" x1="0" y1="0" x2="1" y2="1">
            <stop offset="0" stop-color="#14B8A6" />
            <stop offset="1" stop-color="#0F766E" />
        </linearGradient>
    </defs>
    <rect width="40" height="40" rx="11" fill="url(#{{ $gid }})" />
    {{-- orbit (behind the island) --}}
    <ellipse cx="20" cy="21" rx="15.5" ry="6" transform="rotate(-24 20 21)" fill="none" stroke="#FCD34D" stroke-width="1.6" stroke-opacity=".9" />
    {{-- island --}}
    <path d="M17.6 7.2c1.2-.6 2.8.2 3.3 1.7l.7 2.1c2.3 1.6 4.5 4.5 5.5 8.2 1.2 4.4.2 9.1-3.3 11.7-3 2.2-7.2 1.7-9.2-1.3-1.7-2.5-1.8-6.2-.9-9.6.7-2.7 1.9-5.4 2.7-7.7l.2-3c.1-.9.4-1.7 1-2.1Z" fill="#fff" />
    {{-- front half of the orbit, over the island --}}
    <path d="M6.7 27.9c3.2 2.5 11 1.6 19.2-2.1" fill="none" stroke="#F59E0B" stroke-width="1.8" stroke-linecap="round" />
    {{-- sun --}}
    <circle cx="32.2" cy="12.6" r="3" fill="#F59E0B" />
</svg>
