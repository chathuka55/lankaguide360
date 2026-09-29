<x-mail::message>
# Hello {{ $trip->contactName() }},

@switch($status)
@case(\App\Enums\TripStatus::UnderReview)
An agent has started reviewing your trip **{{ $trip->reference }}**. We'll be in touch soon if anything needs your input.
@break
@case(\App\Enums\TripStatus::Approved)
Good news: your trip **{{ $trip->reference }}** has been approved.

**Final price: ${{ number_format((float) $trip->displayTotal(), 2) }}**@if ($trip->price_note) ({{ $trip->price_note }})@endif

Your day-by-day plan is attached as a PDF. Reply to your agent from the trip page to confirm, or if you'd like any changes.
@break
@case(\App\Enums\TripStatus::Rejected)
Unfortunately we can't offer trip **{{ $trip->reference }}** as planned.
@break
@case(\App\Enums\TripStatus::Confirmed)
Your trip **{{ $trip->reference }}** is confirmed. We're booking your hotels now and will send your driver's details before you travel.
@break
@case(\App\Enums\TripStatus::Cancelled)
Your trip **{{ $trip->reference }}** has been cancelled.
@break
@case(\App\Enums\TripStatus::Completed)
We hope you had a wonderful time in Sri Lanka! We'd love to hear about it: you can leave a review from your trip page.
@break
@default
The status of your trip **{{ $trip->reference }}** is now **{{ $status->label() }}**.
@endswitch

@if ($note)
<x-mail::panel>
{{ $note }}
</x-mail::panel>
@endif

<x-mail::button :url="$trip->privateUrl()">
View your trip
</x-mail::button>

Thanks,<br>
The {{ config('app.name') }} team
</x-mail::message>
