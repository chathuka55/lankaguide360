<x-app-layout title="Plan a Trip" description="Build a day-by-day Sri Lanka itinerary in about five minutes, with a route map and full price breakdown.">
    @push('head')
        @vite(['resources/js/builder.js'])
    @endpush

    <livewire:trip-builder />
</x-app-layout>
