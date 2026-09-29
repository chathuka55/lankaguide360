<x-mail::message>
# New trip request {{ $trip->reference }}

- **Traveller:** {{ $trip->contactName() }} ({{ $trip->contactEmail() }})
- **Dates:** {{ $trip->start_date->format('j M Y') }}, {{ $trip->days }} days
- **Style:** {{ $trip->tier->label() }}
- **Group:** {{ $trip->adults }} adults, {{ $trip->children }} children, {{ $trip->infants }} infants
- **Estimate:** ${{ number_format((float) $trip->estimated_total, 2) }}

<x-mail::button :url="route('admin.trips.show', $trip)">
Open the request
</x-mail::button>
</x-mail::message>
