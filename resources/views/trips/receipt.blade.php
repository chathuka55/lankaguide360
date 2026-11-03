@php
    /** @var \App\Models\TripReceipt $receipt */
    $s = $receipt->snapshot;
    $void = in_array($trip->status, [\App\Enums\TripStatus::Cancelled, \App\Enums\TripStatus::Rejected], true);
    $lines = collect($s['price_lines'] ?? [])->groupBy('category');
    $contact = config('lankaguide.contact');
    $fmtDate = fn (?string $d, string $f = 'D j M Y') => $d ? \Illuminate\Support\Carbon::parse($d)->format($f) : '';
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>E-receipt {{ $receipt->number }} · LankaGuide360</title>
    <style>
        @page { margin: 22px 28px; }
        * { box-sizing: border-box; }
        body { font-family: {{ $forPdf ? 'DejaVu Sans' : "Inter, 'Segoe UI', system-ui, sans-serif" }}; font-size: {{ $forPdf ? '9px' : '14px' }}; color: #1e293b; line-height: 1.45; margin: 0; background: {{ $forPdf ? '#fff' : '#f1f5f9' }}; }
        .page { max-width: 820px; margin: {{ $forPdf ? '0' : '24px auto' }}; background: #fff; padding: {{ $forPdf ? '0' : '32px' }}; {{ $forPdf ? '' : 'border-radius: 16px; box-shadow: 0 1px 3px rgb(0 0 0 / .08);' }} position: relative; }
        table { width: 100%; border-collapse: collapse; }
        th, td { text-align: left; padding: {{ $forPdf ? '2px 5px' : '4px 6px' }}; vertical-align: top; }
        .muted { color: #64748b; }
        .brand { font-size: 1.4em; font-weight: 700; color: #0E8437; }
        .brand span { color: #0B4F8F; }
        .brand img { height: 2.1em; width: 2.1em; vertical-align: middle; margin-right: 6px; }
        .title { font-size: 1.05em; letter-spacing: .08em; text-transform: uppercase; font-weight: 700; color: #0f172a; }
        .ticket { border: 2px dashed #234F3A; border-radius: 12px; padding: {{ $forPdf ? '8px 12px' : '14px 16px' }}; margin-top: {{ $forPdf ? '8px' : '14px' }}; }
        .big { font-size: 1.5em; font-weight: 700; }
        .badge { display: inline-block; padding: 2px 10px; border-radius: 999px; font-weight: 700; font-size: .85em; }
        .ok { background: #ddebe1; color: #1d3f2f; }
        .void { background: #fee2e2; color: #991b1b; }
        h2 { font-size: 1em; margin: {{ $forPdf ? '10px 0 4px' : '18px 0 6px' }}; color: #234F3A; border-bottom: 1px solid #ddebe1; padding-bottom: 3px; text-transform: uppercase; letter-spacing: .05em; }
        .day td { border-bottom: 1px solid #f1f5f9; }
        .dayhead td { background: #f1f6f2; font-weight: 700; }
        .num { text-align: right; white-space: nowrap; }
        .total td { font-weight: 700; border-top: 2px solid #234F3A; font-size: 1.1em; }
        .watermark { position: absolute; top: 38%; left: 8%; right: 8%; text-align: center; font-size: 72px; font-weight: 700; color: rgba(220, 38, 38, .14); transform: rotate(-18deg); }
        .toolbar { max-width: 820px; margin: 24px auto 0; display: flex; gap: 8px; flex-wrap: wrap; }
        .btn { display: inline-block; padding: 9px 16px; border-radius: 10px; font-weight: 600; text-decoration: none; border: 1px solid #cbd5e1; color: #0f172a; background: #fff; cursor: pointer; font-size: 14px; }
        .btn-primary { background: #234F3A; color: #fff; border-color: #234F3A; }
        @media print { body { background: #fff; } .page { margin: 0; box-shadow: none; padding: 0; } .toolbar { display: none; } }
    </style>
</head>
<body>
    @unless ($forPdf)
        <div class="toolbar">
            <button type="button" class="btn btn-primary" onclick="window.print()">Print</button>
            @if (app(\App\Services\TripPdf::class)->available())
                <a class="btn" href="{{ route('trips.receipt.pdf', $trip) }}">Download PDF</a>
            @endif
            <a class="btn" href="{{ route('trips.show', $trip) }}">Back to the trip</a>
        </div>
    @endunless

    <div class="page">
        @if ($void)<div class="watermark">{{ strtoupper($trip->status->label()) }}</div>@endif

        <table>
            <tr>
                <td style="padding: 0">
                    <div class="brand"><img src="{{ $forPdf ? public_path('images/brand/mark-128.png') : asset('images/brand/mark-128.png') }}" alt="">Lanka<span>Guide360</span></div>
                    <div class="muted">{{ $contact['address'] }} · {{ $contact['phone'] }} · {{ $contact['email'] }}</div>
                    <div class="title" style="margin-top: 10px">E-receipt · Booking voucher</div>
                </td>
                <td style="padding: 0; width: {{ $forPdf ? '110px' : '140px' }}; text-align: center">
                    @php($qr = $forPdf ? 92 : 120)
                    <img src="{{ $receipt->qrCodeDataUri() }}" alt="QR code to verify e-receipt {{ $receipt->number }}" width="{{ $qr }}" height="{{ $qr }}" style="width: {{ $qr }}px; height: {{ $qr }}px">
                    <div class="muted" style="font-size: .8em">Scan to verify</div>
                </td>
            </tr>
        </table>

        <div class="ticket">
            <table>
                <tr>
                    <td style="padding-left: 0">
                        <div class="muted">E-receipt number</div>
                        <div class="big">{{ $receipt->number }}</div>
                    </td>
                    <td>
                        <div class="muted">Booking reference</div>
                        <div class="big">{{ $s['reference'] }}</div>
                    </td>
                    <td class="num" style="padding-right: 0">
                        <span class="badge {{ $void ? 'void' : 'ok' }}">{{ $void ? strtoupper($trip->status->label()) : 'CONFIRMED' }}</span>
                        <div class="muted" style="margin-top: 4px">Issued {{ $receipt->issued_at->format('j M Y, H:i') }}@if ($receipt->revision > 1) · version {{ $receipt->revision }} @endif</div>
                    </td>
                </tr>
            </table>
        </div>

        <h2>Booking</h2>
        <table>
            <tr>
                <td style="width: 50%; padding-left: 0">
                    <div class="muted">Travellers</div>
                    @foreach ($s['travellers'] as $traveller)
                        <div>{{ $traveller['name'] }}@if ($traveller['lead']) <span class="muted">(lead)</span>@endif</div>
                    @endforeach
                    <div class="muted" style="margin-top: 4px">{{ $s['adults'] }} {{ Str::plural('adult', $s['adults']) }}@if ($s['children']), {{ $s['children'] }} {{ Str::plural('child', $s['children']) }}@endif @if ($s['infants']), {{ $s['infants'] }} {{ Str::plural('infant', $s['infants']) }}@endif</div>
                </td>
                <td style="width: 50%">
                    <table>
                        <tr><td class="muted" style="padding-left: 0">Dates</td><td>{{ $fmtDate($s['start_date'], 'j M Y') }} – {{ $fmtDate($s['end_date'], 'j M Y') }} ({{ $s['days'] }} days)</td></tr>
                        <tr><td class="muted" style="padding-left: 0">Travel style</td><td>{{ $s['tier'] }}</td></tr>
                        <tr><td class="muted" style="padding-left: 0">Pick-up</td><td>{{ $s['arrival_point'] }}</td></tr>
                        <tr><td class="muted" style="padding-left: 0">Drop-off</td><td>{{ $s['departure_point'] }}</td></tr>
                        <tr><td class="muted" style="padding-left: 0">Vehicle</td><td>{{ $s['vehicle'] ?? 'To be confirmed' }}</td></tr>
                        <tr><td class="muted" style="padding-left: 0">Guide</td><td>{{ $s['guide'] }}</td></tr>
                        <tr><td class="muted" style="padding-left: 0">Meals</td><td>{{ $s['meal_plan'] }}</td></tr>
                        @if ($s['agent'])<tr><td class="muted" style="padding-left: 0">Your agent</td><td>{{ $s['agent'] }}</td></tr>@endif
                    </table>
                </td>
            </tr>
        </table>

        <h2>Final itinerary</h2>
        <table>
            @foreach ($s['itinerary'] as $day)
                <tr class="dayhead"><td colspan="3">Day {{ $day['day'] }} · {{ $fmtDate($day['date']) }} · {{ $day['title'] }}</td></tr>
                @forelse ($day['stops'] as $stop)
                    <tr class="day">
                        <td style="width: 110px; white-space: nowrap">{{ $stop['arrive'] ?? '--:--' }}@if ($stop['depart']) – {{ $stop['depart'] }}@endif</td>
                        <td>{{ $stop['place'] }}</td>
                        <td class="muted">{{ $stop['district'] }}</td>
                    </tr>
                @empty
                    <tr class="day"><td></td><td colspan="2" class="muted">Free day / travel day</td></tr>
                @endforelse
                @if ($day['hotel'] || $day['town'])
                    <tr class="day"><td class="muted">Overnight</td><td colspan="2">{{ $day['hotel'] ?? 'Hotel confirmed by your agent' }}@if ($day['town']), {{ $day['town'] }}@endif @if ($day['room']) <span class="muted">({{ $day['room'] }})</span>@endif</td></tr>
                @endif
            @endforeach
        </table>

        <h2>Payment</h2>
        <table>
            @foreach ($lines as $category => $categoryLines)
                @foreach ($categoryLines as $line)
                    <tr class="day"><td class="muted" style="width: 120px; padding-left: 0">{{ $category }}</td><td>{{ $line['description'] }}</td><td class="num">{{ $receipt->money($line['amount_usd']) }}</td></tr>
                @endforeach
            @endforeach
            <tr class="total"><td colspan="2" style="padding-left: 0">Total{{ $s['price_note'] ? ' ('.$s['price_note'].')' : '' }}</td><td class="num">{{ $receipt->money($receipt->total_usd) }}</td></tr>
            <tr><td colspan="2" style="padding-left: 0">Paid{{ $receipt->payment_method ? ' · '.(\App\Models\TripReceipt::PAYMENT_METHODS[$receipt->payment_method] ?? $receipt->payment_method) : '' }}{{ $receipt->payment_note ? ' · '.$receipt->payment_note : '' }}</td><td class="num">{{ $receipt->money($receipt->paid_usd) }}</td></tr>
            <tr><td colspan="2" style="padding-left: 0; font-weight: 700">{{ $receipt->isPaidInFull() ? 'Paid in full' : 'Balance due' }}</td><td class="num" style="font-weight: 700">{{ $receipt->money($receipt->balanceUsd()) }}</td></tr>
        </table>
        @if ($receipt->currency !== 'USD')
            <p class="muted" style="font-size: .85em">Amounts in {{ $receipt->currency }} at 1 USD = {{ rtrim(rtrim($receipt->exchange_rate, '0'), '.') }} {{ $receipt->currency }} on the issue date. Total in US dollars: USD {{ number_format((float) $receipt->total_usd, 2) }}.</p>
        @endif

        <p class="muted" style="margin-top: 18px; font-size: .85em">
            Show this e-receipt (printed or on your phone) to your driver and at each hotel. Times are planned and may move a little with traffic and weather.
            Check it is genuine at {{ $receipt->verifyUrl() }}
        </p>
    </div>
</body>
</html>
