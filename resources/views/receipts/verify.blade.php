<x-app-layout title="Verify e-receipt" description="Check that a LankaGuide360 e-receipt is genuine.">
    @push('head')<meta name="robots" content="noindex, nofollow">@endpush

    <div class="mx-auto max-w-xl px-4 py-12 sm:px-6">
        @if ($valid)
            <div class="rounded-2xl border-2 border-primary-600 bg-primary-50 p-6 text-center" role="status">
                <span class="mx-auto grid size-14 place-items-center rounded-full bg-primary-700 text-white"><x-icon name="check" class="size-8" /></span>
                <h1 class="mt-4 text-2xl font-extrabold text-primary-900">Genuine booking</h1>
                <p class="mt-1 text-primary-900">This e-receipt was issued by LankaGuide360 and the booking is active.</p>
            </div>
        @elseif ($genuine)
            <div class="rounded-2xl border-2 border-red-300 bg-red-50 p-6 text-center" role="alert">
                <h1 class="text-2xl font-extrabold text-red-800">No longer valid</h1>
                <p class="mt-1 text-red-800">This e-receipt is genuine, but the booking was {{ strtolower($trip->status->label()) }}.</p>
            </div>
        @else
            <div class="rounded-2xl border-2 border-red-300 bg-red-50 p-6 text-center" role="alert">
                <h1 class="text-2xl font-extrabold text-red-800">Not recognised</h1>
                <p class="mt-1 text-red-800">We could not verify e-receipt {{ $number }}. Please contact LankaGuide360 on {{ config('lankaguide.contact.phone') }}.</p>
            </div>
        @endif

        @if ($genuine)
            <dl class="mt-6 divide-y divide-slate-100 rounded-2xl border border-slate-200 bg-white text-sm">
                <div class="flex justify-between gap-4 p-4"><dt class="text-slate-500">E-receipt</dt><dd class="font-mono font-semibold">{{ $receipt->number }}</dd></div>
                <div class="flex justify-between gap-4 p-4"><dt class="text-slate-500">Booking</dt><dd class="font-mono">{{ $trip->reference }}</dd></div>
                <div class="flex justify-between gap-4 p-4"><dt class="text-slate-500">Lead traveller</dt><dd>{{ $initials }}</dd></div>
                <div class="flex justify-between gap-4 p-4"><dt class="text-slate-500">Travellers</dt><dd>{{ $trip->travellerCount() }}</dd></div>
                <div class="flex justify-between gap-4 p-4"><dt class="text-slate-500">Dates</dt><dd>{{ $trip->start_date?->format('j M Y') }} – {{ $trip->start_date?->copy()->addDays(max($trip->days - 1, 0))->format('j M Y') }}</dd></div>
                <div class="flex justify-between gap-4 p-4"><dt class="text-slate-500">Status</dt><dd><x-admin.status-badge :status="$trip->status" /></dd></div>
                <div class="flex justify-between gap-4 p-4"><dt class="text-slate-500">Issued</dt><dd>{{ $receipt->issued_at->format('j M Y, H:i') }}@if ($receipt->revision > 1) (version {{ $receipt->revision }}) @endif</dd></div>
            </dl>
        @endif
    </div>
</x-app-layout>
