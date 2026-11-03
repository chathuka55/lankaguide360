@php
    use App\Support\Money;
    $percent = fn ($v) => $v === null ? '—' : rtrim(rtrim(number_format($v, 1), '0'), '.').'%';
    $maxRequests = max(1, $monthly->max('requests'));
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>LankaGuide360 report</title>
    <style>
        @page { margin: 26px 32px; }
        body { font-family: DejaVu Sans, sans-serif; font-size: 10px; color: #1e293b; line-height: 1.4; }
        h1 { font-size: 18px; margin: 0; color: #234F3A; }
        h2 { font-size: 12px; margin: 16px 0 6px; color: #234F3A; border-bottom: 1px solid #ddebe1; padding-bottom: 3px; }
        .muted { color: #64748b; }
        .header { border-bottom: 3px solid #234F3A; padding-bottom: 8px; margin-bottom: 10px; }
        .brand { font-size: 15px; font-weight: bold; color: #0E8437; }
        table { width: 100%; border-collapse: collapse; }
        th, td { text-align: left; padding: 3px 5px; vertical-align: top; }
        th { background: #f1f6f2; font-size: 9px; text-transform: uppercase; color: #234F3A; }
        tbody tr td { border-bottom: 1px solid #f1f5f9; }
        .num { text-align: right; white-space: nowrap; }
        .tiles td { width: 25%; border: 1px solid #e2e8f0; padding: 6px 8px; }
        .tile-value { font-size: 15px; font-weight: bold; }
        .bar { background: #234F3A; height: 8px; }
        .page-break { page-break-before: always; }
    </style>
</head>
<body>
    <div class="header">
        <div class="brand"><img src="{{ public_path('images/brand/mark-128.png') }}" alt="" style="height: 30px; width: 30px; vertical-align: middle; margin-right: 6px">Lanka<span style="color: #0B4F8F">Guide360</span></div>
        <h1>Business report</h1>
        <div class="muted">
            Trip requests submitted {{ $reports->from->format('j M Y') }} – {{ $reports->to->format('j M Y') }}{{ $reports->tier ? ' · '.$reports->tier->label() : '' }}.
            Amounts in {{ Money::displayCurrency() }}. Generated {{ now()->format('j M Y H:i') }}.
        </div>
    </div>

    <table class="tiles">
        <tr>
            <td><div class="muted">Trip requests</div><div class="tile-value">{{ number_format($summary['requests']) }}</div>{{ $summary['open'] }} waiting</td>
            <td><div class="muted">Booked trips</div><div class="tile-value">{{ number_format($summary['booked']) }}</div>{{ $percent($summary['booking_rate']) }} of requests</td>
            <td><div class="muted">Booked revenue</div><div class="tile-value">{{ Money::show($summary['revenue']) }}</div>{{ Money::approxUsd($summary['revenue']) }}</td>
            <td><div class="muted">Approved, not confirmed</div><div class="tile-value">{{ Money::show($summary['pipeline']) }}</div>&nbsp;</td>
        </tr>
        <tr>
            <td><div class="muted">Average booked trip</div><div class="tile-value">{{ $summary['average_value'] ? Money::show($summary['average_value']) : '—' }}</div>&nbsp;</td>
            <td><div class="muted">Approval rate</div><div class="tile-value">{{ $percent($summary['approval_rate']) }}</div>{{ $summary['rejected'] }} rejected · {{ $summary['cancelled'] }} cancelled</td>
            <td><div class="muted">First response</div><div class="tile-value">{{ $summary['response_hours'] === null ? '—' : $summary['response_hours'].' h' }}</div>average</td>
            <td><div class="muted">Travellers</div><div class="tile-value">{{ number_format($summary['travellers']) }}</div>{{ $summary['guests'] }} guest requests</td>
        </tr>
    </table>

    <h2>Requests and revenue by month</h2>
    <table>
        <thead><tr><th>Month</th><th style="width: 40%"></th><th class="num">Requests</th><th class="num">Booked</th><th class="num">Booked revenue</th></tr></thead>
        <tbody>
            @foreach ($monthly as $row)
                <tr>
                    <td>{{ $row['label'] }}</td>
                    <td><div class="bar" style="width: {{ $row['requests'] / $maxRequests * 100 }}%"></div></td>
                    <td class="num">{{ $row['requests'] }}</td>
                    <td class="num">{{ $row['booked'] }}</td>
                    <td class="num">{{ Money::show($row['revenue']) }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <h2>Status and travel style</h2>
    <table>
        <tr>
            <td style="width: 50%; padding: 0 8px 0 0">
                <table>
                    <thead><tr><th>Status</th><th class="num">Trips</th><th class="num">Value</th></tr></thead>
                    <tbody>@foreach ($status as $row)<tr><td>{{ $row['label'] }}</td><td class="num">{{ $row['trips'] }}</td><td class="num">{{ Money::show($row['value']) }}</td></tr>@endforeach</tbody>
                </table>
            </td>
            <td style="width: 50%; padding: 0 0 0 8px">
                <table>
                    <thead><tr><th>Style</th><th class="num">Requests</th><th class="num">Booked</th><th class="num">Revenue</th></tr></thead>
                    <tbody>@foreach ($tierRows as $row)<tr><td>{{ $row['label'] }}</td><td class="num">{{ $row['trips'] }}</td><td class="num">{{ $row['booked'] }}</td><td class="num">{{ Money::show($row['revenue']) }}</td></tr>@endforeach</tbody>
                </table>
            </td>
        </tr>
    </table>

    <h2>Most requested places</h2>
    <table>
        <thead><tr><th>Place</th><th>District</th><th class="num">Trips</th><th class="num">Booked</th></tr></thead>
        <tbody>
            @forelse ($places as $row)
                <tr><td>{{ $row['place'] }}</td><td>{{ $row['district'] }}</td><td class="num">{{ $row['trips'] }}</td><td class="num">{{ $row['booked'] }}</td></tr>
            @empty
                <tr><td colspan="4" class="muted">No trip requests in this period.</td></tr>
            @endforelse
        </tbody>
    </table>

    <h2>Agent performance</h2>
    <table>
        <thead><tr><th>Agent</th><th class="num">Assigned</th><th class="num">Approved</th><th class="num">Rejected</th><th class="num">Booked</th><th class="num">Revenue</th><th class="num">First response</th></tr></thead>
        <tbody>
            @foreach ($agents as $row)
                <tr>
                    <td>{{ $row['agent'] }}</td><td class="num">{{ $row['assigned'] }}</td><td class="num">{{ $row['approved'] ?? '—' }}</td><td class="num">{{ $row['rejected'] ?? '—' }}</td>
                    <td class="num">{{ $row['booked'] ?? '—' }}</td><td class="num">{{ $row['revenue'] === null ? '—' : Money::show($row['revenue']) }}</td>
                    <td class="num">{{ $row['response_hours'] === null ? '—' : $row['response_hours'].' h' }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <h2>Districts and traveller countries</h2>
    <table>
        <tr>
            <td style="width: 50%; padding: 0 8px 0 0">
                <table>
                    <thead><tr><th>District</th><th class="num">Trips</th></tr></thead>
                    <tbody>@forelse ($districts as $row)<tr><td>{{ $row['district'] }}</td><td class="num">{{ $row['trips'] }}</td></tr>@empty<tr><td colspan="2" class="muted">—</td></tr>@endforelse</tbody>
                </table>
            </td>
            <td style="width: 50%; padding: 0 0 0 8px">
                <table>
                    <thead><tr><th>Country</th><th class="num">Trips</th></tr></thead>
                    <tbody>@forelse ($countries as $row)<tr><td>{{ $row['country'] }}</td><td class="num">{{ $row['trips'] }}</td></tr>@empty<tr><td colspan="2" class="muted">—</td></tr>@endforelse</tbody>
                </table>
            </td>
        </tr>
    </table>

    <div class="page-break"></div>
    <h2>Data readiness by district</h2>
    <p class="muted">Ready = a published place with a location, and a bookable hotel (published, with room rates) in every travel style.</p>
    <table>
        <thead><tr><th>District</th><th class="num">Places</th><th class="num">Published</th><th class="num">Hotels</th><th class="num">Budget</th><th class="num">Premium</th><th class="num">Luxury</th><th>Ready</th></tr></thead>
        <tbody>
            @foreach ($content as $row)
                <tr>
                    <td>{{ $row['district'] }}</td><td class="num">{{ $row['places'] }}</td><td class="num">{{ $row['published_places'] }}</td><td class="num">{{ number_format($row['hotels']) }}</td>
                    <td class="num">{{ $row['bookable_budget'] }}</td><td class="num">{{ $row['bookable_premium'] }}</td><td class="num">{{ $row['bookable_luxury'] }}</td><td>{{ $row['ready'] ? 'Yes' : 'No' }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
</body>
</html>
