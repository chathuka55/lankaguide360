<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Trip {{ $trip->reference }}</title>
    <style>
        @page { margin: 28px 34px; }
        body { font-family: DejaVu Sans, sans-serif; font-size: 11px; color: #1e293b; line-height: 1.45; }
        h1 { font-size: 20px; margin: 0; color: #0F766E; }
        h2 { font-size: 14px; margin: 18px 0 6px; color: #0F766E; border-bottom: 1px solid #ccfbf1; padding-bottom: 3px; }
        h3 { font-size: 12px; margin: 0 0 4px; }
        .muted { color: #64748b; }
        .header { border-bottom: 3px solid #0F766E; padding-bottom: 8px; margin-bottom: 12px; }
        .brand { font-size: 12px; font-weight: bold; color: #F59E0B; }
        table { width: 100%; border-collapse: collapse; }
        th, td { text-align: left; padding: 4px 6px; vertical-align: top; }
        th { background: #f0fdfa; font-size: 10px; text-transform: uppercase; color: #0f766e; }
        tr.line td { border-bottom: 1px solid #f1f5f9; }
        .num { text-align: right; white-space: nowrap; }
        .day { page-break-inside: avoid; border: 1px solid #e2e8f0; border-radius: 6px; padding: 8px 10px; margin-bottom: 8px; }
        .total td { font-weight: bold; font-size: 13px; border-top: 2px solid #0F766E; }
        .facts td { padding: 2px 6px 2px 0; }
        .footer { margin-top: 20px; font-size: 9px; color: #64748b; }
    </style>
</head>
<body>
    <div class="header">
        <div class="brand">LankaGuide360</div>
        <h1>{{ $trip->days }}-day Sri Lanka trip · {{ $trip->reference }}</h1>
        <div class="muted">
            {{ $trip->start_date?->format('j F Y') }} – {{ $trip->start_date?->copy()->addDays(max($trip->days - 1, 0))->format('j F Y') }}
            · {{ $trip->tier->label() }} · Status: {{ $trip->status->label() }}
        </div>
    </div>

    <table class="facts">
        <tr><td class="muted">Lead traveller</td><td>{{ $trip->contactName() }}</td><td class="muted">Travellers</td><td>{{ $trip->adults }} adults, {{ $trip->children }} children, {{ $trip->infants }} infants</td></tr>
        <tr><td class="muted">Arrival</td><td>{{ $trip->arrival_point }}</td><td class="muted">Departure</td><td>{{ $trip->departure_point }}</td></tr>
        <tr><td class="muted">Vehicle</td><td>{{ $trip->vehicle?->type ?? 'To be confirmed' }}</td><td class="muted">Guide</td><td>{{ $trip->guide_type->label() }} ({{ $trip->guide_language }})</td></tr>
        <tr><td class="muted">Meals</td><td>{{ $trip->meal_plan->label() }}</td><td class="muted">Agent</td><td>{{ $trip->agent?->name ?? 'LankaGuide360 team' }}</td></tr>
    </table>

    <h2>Day by day</h2>
    @foreach ($trip->tripDays as $day)
        <div class="day">
            <h3>Day {{ $day->day_number }} · {{ $day->date?->format('D j M') }} · {{ $day->title }}</h3>
            @if ($day->stops->isNotEmpty())
                <table>
                    @foreach ($day->stops as $stop)
                        <tr>
                            <td style="width: 44px" class="muted">{{ $stop->arrive_at ? substr($stop->arrive_at, 0, 5) : '' }}</td>
                            <td>{{ $stop->place?->name }} <span class="muted">· {{ $stop->place?->district?->name }}</span></td>
                            <td class="num muted">@if ((float) $stop->km_from_prev > 0){{ number_format((float) $stop->km_from_prev) }} km @endif</td>
                        </tr>
                    @endforeach
                </table>
            @else
                <div class="muted">Free day or travel day.</div>
            @endif
            <div class="muted" style="margin-top: 4px">
                @if ($day->overnight_town || $day->hotel)
                    Overnight: {{ $day->hotel?->name ?? 'hotel to be confirmed' }}{{ $day->overnight_town ? ', '.$day->overnight_town : '' }}{{ $day->roomRate ? ' ('.$day->roomRate->room_type.')' : '' }}.
                @endif
                @if ((float) $day->drive_km > 0) Driving about {{ number_format((float) $day->drive_km) }} km. @endif
            </div>
        </div>
    @endforeach

    <h2>Price</h2>
    <table>
        <thead><tr><th>Item</th><th class="num">Qty</th><th class="num">Unit (USD)</th><th class="num">Amount (USD)</th></tr></thead>
        <tbody>
            @foreach ($trip->priceItems as $item)
                <tr class="line">
                    <td>{{ $item->category->label() }}: {{ $item->description }}</td>
                    <td class="num">{{ rtrim(rtrim($item->qty, '0'), '.') }}</td>
                    <td class="num">{{ number_format((float) $item->unit_price, 2) }}</td>
                    <td class="num">{{ number_format((float) $item->amount, 2) }}</td>
                </tr>
            @endforeach
            <tr class="total">
                <td colspan="3">{{ $trip->final_total !== null ? 'Final price' : 'Estimated total' }}{{ $trip->price_note ? ' ('.$trip->price_note.')' : '' }}</td>
                <td class="num">${{ number_format((float) $trip->displayTotal(), 2) }}</td>
            </tr>
        </tbody>
    </table>

    @if ($trip->special_requests)
        <h2>Special requests</h2>
        <p>{{ $trip->special_requests }}</p>
    @endif

    <div class="footer">
        Times are indicative and depend on traffic and weather. Prices in US dollars.
        {{ config('app.name') }} · {{ config('lankaguide.contact.email') }} · {{ config('lankaguide.contact.phone') }}
        · Generated {{ now()->format('j M Y H:i') }}
    </div>
</body>
</html>
