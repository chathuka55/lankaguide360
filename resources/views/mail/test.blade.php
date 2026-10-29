<x-mail::message>
# Email is working

This test email from {{ config('app.name') }} arrived, so travellers and agents will receive trip confirmations, status updates, messages and e-receipts.

- **Sent with:** {{ $mailer }}@if ($host) ({{ $host }})@endif
- **From:** {{ $from }}
- **Sent at:** {{ now()->format('j M Y, H:i') }}
</x-mail::message>
