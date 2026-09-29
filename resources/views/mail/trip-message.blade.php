<x-mail::message>
# New message about trip {{ $tripMessage->trip->reference }}

<x-mail::panel>
{{ $tripMessage->body }}
</x-mail::panel>

<x-mail::button :url="$url">
Read and reply
</x-mail::button>

The {{ config('app.name') }} team
</x-mail::message>
