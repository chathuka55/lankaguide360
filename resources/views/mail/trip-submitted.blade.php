<x-mail::message>
# Thank you, {{ $trip->contactName() }}!

We have received your trip request. Your reference number is **{{ $trip->reference }}**.

**{{ $trip->days }} days from {{ $trip->start_date->format('j F Y') }}** · {{ $trip->tier->label() }} · {{ $trip->travellerCount() }} {{ Str::plural('traveller', $trip->travellerCount()) }}

<x-mail::table>
| Day | Plan |
|:----|:-----|
@foreach ($days as $day)
| {{ $day->day_number }} | {{ $day->title }}@if ($day->stops->isNotEmpty()): {{ $day->stops->map(fn ($s) => $s->place?->name)->filter()->implode(', ') }}@endif |
@endforeach
</x-mail::table>

**Estimated total: ${{ number_format((float) $trip->estimated_total, 2) }}** — this is an estimate. A local travel agent will check availability and confirm the final price, usually within one working day.

<x-mail::button :url="$trip->privateUrl()">
View your trip
</x-mail::button>

Keep this email: the button above is your private link to follow the status of your trip and message your agent.

Thanks,<br>
The {{ config('app.name') }} team
</x-mail::message>
