{{-- Shared <head> contents for the public, admin and auth layouts. --}}
@php
    $pageTitle = $title ? $title.' · '.config('app.name') : config('app.name').' · Sri Lanka Trip Planner';
    $pageDescription = $description ?? 'Plan a day-by-day Sri Lanka trip in minutes: choose places, hotels and transport, see your route on a map and get a price confirmed by a local travel agent.';
    $pageImage = $image ?? null;
@endphp
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="csrf-token" content="{{ csrf_token() }}">

<title>{{ $pageTitle }}</title>
<meta name="description" content="{{ Str::limit($pageDescription, 160) }}">
<meta name="theme-color" content="#0F766E">
<link rel="canonical" href="{{ url()->current() }}">

{{-- Open Graph / social previews (SRS NFR-13) --}}
<meta property="og:site_name" content="{{ config('app.name') }}">
<meta property="og:type" content="{{ $ogType ?? 'website' }}">
<meta property="og:title" content="{{ $title ?? config('app.name') }}">
<meta property="og:description" content="{{ Str::limit($pageDescription, 200) }}">
<meta property="og:url" content="{{ url()->current() }}">
@if ($pageImage)
    <meta property="og:image" content="{{ $pageImage }}">
    <meta name="twitter:card" content="summary_large_image">
@else
    <meta name="twitter:card" content="summary">
@endif

<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">

@livewireStyles
@vite(['resources/css/app.css', 'resources/js/app.js'])
@stack('head')
