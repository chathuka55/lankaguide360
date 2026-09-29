<x-mail::message>
# New contact message

**From:** {{ $contactMessage->name }} ({{ $contactMessage->email }})
@if ($contactMessage->phone)
**Phone:** {{ $contactMessage->phone }}
@endif
@if ($contactMessage->subject)
**Subject:** {{ $contactMessage->subject }}
@endif

{{ $contactMessage->body }}

<x-mail::button :url="route('admin.messages.show', $contactMessage)">
Open in the admin panel
</x-mail::button>

Reply to this email to answer {{ $contactMessage->name }} directly.
</x-mail::message>
