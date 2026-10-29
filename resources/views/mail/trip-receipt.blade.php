<x-mail::message>
# Your booking is final

Hello {{ $trip->contactName() }},

Your trip **{{ $trip->reference }}** is confirmed and every timing is final. Your e-receipt **{{ $receipt->number }}** is attached as a PDF{{ $receipt->revision > 1 ? ' (updated version '.$receipt->revision.')' : '' }}.

- **Dates:** {{ $trip->start_date->format('j F Y') }}, {{ $trip->days }} days
- **Total:** {{ $receipt->money($receipt->total_usd) }}
- **Paid:** {{ $receipt->money($receipt->paid_usd) }}
@if (! $receipt->isPaidInFull())
- **Balance due:** {{ $receipt->money($receipt->balanceUsd()) }}
@endif

Show the e-receipt (printed or on your phone) to your driver and at the hotels. Its QR code lets anyone check that the booking is genuine.

<x-mail::button :url="route('trips.receipt', ['trip' => $trip->reference, 'token' => $trip->access_token])">
View and print your e-receipt
</x-mail::button>

Have a wonderful time in Sri Lanka!
</x-mail::message>
