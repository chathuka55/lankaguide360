<x-mail::message>
# Booking accepted: {{ $trip->reference }}

{{ $trip->contactName() }} accepted the final plan and price (@money($trip->displayTotal())).

- **Dates:** {{ $trip->start_date->format('j M Y') }}, {{ $trip->days }} days
- **Style:** {{ $trip->tier->label() }}
- **Group:** {{ $trip->adults }} adults, {{ $trip->children }} children, {{ $trip->infants }} infants

Next: book the hotels, set the final time at every place, and issue the e-receipt.

<x-mail::button :url="route('admin.trips.show', $trip).'#finalize'">
Finalize and issue the e-receipt
</x-mail::button>
</x-mail::message>
