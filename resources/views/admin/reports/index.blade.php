@php
    use App\Support\Money;

    $query = array_filter($filters);
    $csv = fn (string $report) => route('admin.reports.export', ['report' => $report, ...$query]);
    $xlsx = fn (string $report) => route('admin.reports.export', ['report' => $report, ...$query, 'format' => 'xlsx']);
    $money = fn ($usd) => Money::show($usd);
    $currency = Money::displayCurrency();
    $percent = fn ($v) => $v === null ? '—' : rtrim(rtrim(number_format($v, 1), '0'), '.').'%';
    $ranges = [
        'Last 30 days' => [now()->subDays(29)->toDateString(), now()->toDateString()],
        'This year' => [now()->startOfYear()->toDateString(), now()->toDateString()],
        'Last 12 months' => [now()->subMonthsNoOverflow(11)->startOfMonth()->toDateString(), now()->toDateString()],
    ];
    $tiles = [
        ['Trip requests', number_format($summary['requests']), $summary['open'].' waiting for an agent'],
        ['Booked trips', number_format($summary['booked']), $percent($summary['booking_rate']).' of requests'],
        ['Booked revenue', $money($summary['revenue']), Money::approxUsd($summary['revenue'])],
        ['Approved, not yet confirmed', $money($summary['pipeline']), 'Quoted value waiting for travellers'],
        ['Average booked trip', $summary['average_value'] ? $money($summary['average_value']) : '—', $summary['average_days'] ? 'Requests average '.$summary['average_days'].' days' : ''],
        ['Approval rate', $percent($summary['approval_rate']), $summary['rejected'].' rejected · '.$summary['cancelled'].' cancelled'],
        ['First response', $summary['response_hours'] === null ? '—' : $summary['response_hours'].' h', 'Average time to an agent\'s first action'],
        ['Travellers', number_format($summary['travellers']), $summary['guests'].' requests from guests'],
    ];
@endphp

<x-admin-layout title="Reports">
    <x-admin.header title="Reports" :subtitle="'Trip requests submitted '.$reports->from->format('j M Y').' – '.$reports->to->format('j M Y').($reports->tier ? ' · '.$reports->tier->label() : '').'. Amounts in '.$currency.' (stored in USD, converted at the rate in Settings).'">
        <a href="{{ route('admin.reports.workbook', $query) }}" class="btn btn-primary"><x-icon name="download" class="size-4" /> Analytics workbook (Excel)</a>
        <a href="{{ route('admin.reports.pdf', $query) }}" class="btn btn-secondary"><x-icon name="download" class="size-4" /> PDF report</a>
        <a href="{{ $xlsx('trips') }}" class="btn btn-secondary"><x-icon name="download" class="size-4" /> All trips (Excel)</a>
        <a href="{{ $csv('trips') }}" class="btn btn-secondary">CSV</a>
    </x-admin.header>

    {{-- Filters: one row above the charts --}}
    <form method="GET" action="{{ route('admin.reports.index') }}" class="admin-card mb-6 flex flex-col gap-3 p-4 lg:flex-row lg:items-end">
        <div>
            <label for="from" class="form-label">From</label>
            <input type="date" id="from" name="from" value="{{ $filters['from'] }}" class="form-control">
        </div>
        <div>
            <label for="to" class="form-label">To</label>
            <input type="date" id="to" name="to" value="{{ $filters['to'] }}" class="form-control">
        </div>
        <div class="lg:w-44">
            <label for="tier" class="form-label">Travel style</label>
            <select id="tier" name="tier" class="form-control">
                <option value="">All</option>
                @foreach ($tiers as $value => $label)
                    <option value="{{ $value }}" @selected($filters['tier'] === $value)>{{ $label }}</option>
                @endforeach
            </select>
        </div>
        <button type="submit" class="btn btn-primary">Update</button>
        <div class="flex flex-wrap gap-1 lg:ms-auto">
            @foreach ($ranges as $label => [$from, $to])
                <a href="{{ route('admin.reports.index', array_filter(['from' => $from, 'to' => $to, 'tier' => $filters['tier']])) }}"
                   @class(['rounded-full px-3 py-1.5 text-xs font-medium', 'bg-slate-900 text-white' => $filters['from'] === $from && $filters['to'] === $to, 'bg-slate-100 text-slate-700 hover:bg-slate-200' => ! ($filters['from'] === $from && $filters['to'] === $to)])>{{ $label }}</a>
            @endforeach
        </div>
    </form>
    @if ($errors->any())
        <div class="mb-6 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700" role="alert">{{ $errors->first() }}</div>
    @endif

    {{-- Headline numbers --}}
    <dl class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        @foreach ($tiles as [$label, $value, $note])
            <div class="admin-card p-5">
                <dt class="text-sm text-slate-500">{{ $label }}</dt>
                <dd class="mt-2 text-2xl font-bold tabular-nums text-slate-900">{{ $value }}</dd>
                @if ($note)<dd class="mt-1 text-xs text-slate-500">{{ $note }}</dd>@endif
            </div>
        @endforeach
    </dl>

    <div class="mt-6 grid gap-6 xl:grid-cols-2">
        <section class="admin-card p-5" aria-labelledby="r-requests">
            <div class="mb-4 flex items-baseline justify-between gap-2">
                <h3 id="r-requests" class="font-semibold">Trip requests per month</h3>
                <span class="text-xs font-medium text-primary-700"><a href="{{ $csv('monthly') }}" class="hover:underline">CSV</a> · <a href="{{ $xlsx('monthly') }}" class="hover:underline">Excel</a></span>
            </div>
            <x-admin.column-chart :rows="$monthly" value-key="requests" title="Trip requests" />
        </section>

        <section class="admin-card p-5" aria-labelledby="r-revenue">
            <div class="mb-4 flex items-baseline justify-between gap-2">
                <h3 id="r-revenue" class="font-semibold">Booked revenue per month ({{ $currency }})</h3>
                <span class="text-xs font-medium text-primary-700"><a href="{{ $csv('monthly') }}" class="hover:underline">CSV</a> · <a href="{{ $xlsx('monthly') }}" class="hover:underline">Excel</a></span>
            </div>
            <x-admin.column-chart :rows="$monthly->map(fn ($r) => [...$r, 'converted' => Money::convert(Money::cents($r['revenue']), $currency) / 100])" value-key="converted" title="Booked revenue"
                                  :format="fn ($v) => $v >= 1000000 ? number_format($v / 1000000, 1).'M' : ($v >= 1000 ? number_format($v / 1000, $v >= 10000 ? 0 : 1).'k' : number_format($v))" />
        </section>

        <section class="admin-card p-5" aria-labelledby="r-status">
            <div class="mb-4 flex items-baseline justify-between gap-2">
                <h3 id="r-status" class="font-semibold">Trips by status</h3>
                <span class="text-xs font-medium text-primary-700"><a href="{{ $csv('status') }}" class="hover:underline">CSV</a> · <a href="{{ $xlsx('status') }}" class="hover:underline">Excel</a></span>
            </div>
            <x-admin.bar-list :rows="$status->map(fn ($r) => ['label' => $r['label'], 'value' => $r['trips'], 'display' => number_format($r['trips'])])" />
        </section>

        <section class="admin-card p-5" aria-labelledby="r-tiers">
            <div class="mb-4 flex items-baseline justify-between gap-2">
                <h3 id="r-tiers" class="font-semibold">Travel style</h3>
                <span class="text-xs font-medium text-primary-700"><a href="{{ $csv('tiers') }}" class="hover:underline">CSV</a> · <a href="{{ $xlsx('tiers') }}" class="hover:underline">Excel</a></span>
            </div>
            <x-admin.bar-list :rows="$tierRows->map(fn ($r) => ['label' => $r['label'], 'value' => $r['trips'], 'display' => number_format($r['trips']).' requests', 'note' => $r['booked'].' booked · '.$money($r['revenue'])])" />
        </section>

        <section class="admin-card p-5" aria-labelledby="r-places">
            <div class="mb-4 flex items-baseline justify-between gap-2">
                <h3 id="r-places" class="font-semibold">Most requested places</h3>
                <span class="text-xs font-medium text-primary-700"><a href="{{ $csv('places') }}" class="hover:underline">CSV</a> · <a href="{{ $xlsx('places') }}" class="hover:underline">Excel</a> (top 100)</span>
            </div>
            <x-admin.bar-list :rows="$places->map(fn ($r) => ['label' => $r['place'], 'note' => $r['district'], 'value' => $r['trips'], 'display' => $r['trips'].' '.Str::plural('trip', $r['trips'])])" />
        </section>

        <div class="space-y-6">
            <section class="admin-card p-5" aria-labelledby="r-districts">
                <div class="mb-4 flex items-baseline justify-between gap-2">
                    <h3 id="r-districts" class="font-semibold">Most requested districts</h3>
                    <span class="text-xs font-medium text-primary-700"><a href="{{ $csv('districts') }}" class="hover:underline">CSV</a> · <a href="{{ $xlsx('districts') }}" class="hover:underline">Excel</a></span>
                </div>
                <x-admin.bar-list :rows="$districts->map(fn ($r) => ['label' => $r['district'], 'value' => $r['trips'], 'display' => $r['trips'].' '.Str::plural('trip', $r['trips'])])" />
            </section>
            <section class="admin-card p-5" aria-labelledby="r-countries">
                <div class="mb-4 flex items-baseline justify-between gap-2">
                    <h3 id="r-countries" class="font-semibold">Where travellers come from</h3>
                    <span class="text-xs font-medium text-primary-700"><a href="{{ $csv('countries') }}" class="hover:underline">CSV</a> · <a href="{{ $xlsx('countries') }}" class="hover:underline">Excel</a></span>
                </div>
                <x-admin.bar-list :rows="$countries->map(fn ($r) => ['label' => $r['country'], 'value' => $r['trips'], 'display' => $r['trips'].' '.Str::plural('trip', $r['trips'])])" />
            </section>
        </div>
    </div>

    {{-- Tables --}}
    <section class="admin-card mt-6 overflow-x-auto" aria-labelledby="r-agents">
        <div class="flex items-baseline justify-between gap-2 p-5 pb-0">
            <h3 id="r-agents" class="font-semibold">Agent performance</h3>
            <span class="text-xs font-medium text-primary-700"><a href="{{ $csv('agents') }}" class="hover:underline">CSV</a> · <a href="{{ $xlsx('agents') }}" class="hover:underline">Excel</a></span>
        </div>
        <table class="admin-table mt-3">
            <thead><tr><th>Agent</th><th class="text-end">Assigned</th><th class="text-end">Open</th><th class="text-end">Approved</th><th class="text-end">Rejected</th><th class="text-end">Booked</th><th class="text-end">Booked revenue</th><th class="text-end">First response</th></tr></thead>
            <tbody class="divide-y divide-slate-100">
                @forelse ($agents as $row)
                    <tr>
                        <td>{{ $row['agent'] }} <span class="text-xs text-slate-500">{{ $row['role'] }}</span></td>
                        <td class="text-end tabular-nums">{{ $row['assigned'] }}</td>
                        <td class="text-end tabular-nums">{{ $row['open'] ?? '—' }}</td>
                        <td class="text-end tabular-nums">{{ $row['approved'] ?? '—' }}</td>
                        <td class="text-end tabular-nums">{{ $row['rejected'] ?? '—' }}</td>
                        <td class="text-end tabular-nums">{{ $row['booked'] ?? '—' }}</td>
                        <td class="text-end tabular-nums">{{ $row['revenue'] === null ? '—' : $money($row['revenue']) }}</td>
                        <td class="text-end tabular-nums">{{ $row['response_hours'] === null ? '—' : $row['response_hours'].' h' }}</td>
                    </tr>
                @empty
                    <tr><td colspan="8"><x-admin.empty message="No agents yet." /></td></tr>
                @endforelse
            </tbody>
        </table>
    </section>

    <section class="admin-card mt-6 overflow-x-auto" aria-labelledby="r-hotels">
        <div class="flex items-baseline justify-between gap-2 p-5 pb-0">
            <h3 id="r-hotels" class="font-semibold">Most chosen hotels</h3>
            <span class="text-xs font-medium text-primary-700"><a href="{{ $csv('hotels') }}" class="hover:underline">CSV</a> · <a href="{{ $xlsx('hotels') }}" class="hover:underline">Excel</a> (top 100)</span>
        </div>
        <table class="admin-table mt-3">
            <thead><tr><th>Hotel</th><th>Town</th><th>Tier</th><th class="text-end">Nights</th><th class="text-end">Trips</th></tr></thead>
            <tbody class="divide-y divide-slate-100">
                @forelse ($hotels as $row)
                    <tr><td>{{ $row['hotel'] }}</td><td>{{ $row['town'] }}</td><td>{{ $row['tier'] }}</td><td class="text-end tabular-nums">{{ $row['nights'] }}</td><td class="text-end tabular-nums">{{ $row['trips'] }}</td></tr>
                @empty
                    <tr><td colspan="5"><x-admin.empty message="No hotel nights in trip requests for this period." /></td></tr>
                @endforelse
            </tbody>
        </table>
    </section>

    <section class="admin-card mt-6 overflow-x-auto" aria-labelledby="r-content">
        <div class="flex flex-wrap items-baseline justify-between gap-2 p-5 pb-0">
            <div>
                <h3 id="r-content" class="font-semibold">Data readiness by district</h3>
                <p class="text-xs text-slate-500">Not limited to the period. A district is ready when it has a published place with a location and a bookable hotel (published, with room rates) in every travel style.</p>
            </div>
            <span class="text-xs font-medium text-primary-700"><a href="{{ $csv('content') }}" class="hover:underline">CSV</a> · <a href="{{ $xlsx('content') }}" class="hover:underline">Excel</a></span>
        </div>
        <table class="admin-table mt-3">
            <thead><tr><th>District</th><th class="text-end">Places</th><th class="text-end">Published</th><th class="text-end">Hotels</th><th class="text-end">Bookable budget</th><th class="text-end">Bookable premium</th><th class="text-end">Bookable luxury</th><th>Ready</th></tr></thead>
            <tbody class="divide-y divide-slate-100">
                @foreach ($content as $row)
                    <tr>
                        <td>{{ $row['district'] }}</td>
                        <td class="text-end tabular-nums">{{ $row['places'] }}</td>
                        <td class="text-end tabular-nums">{{ $row['published_places'] }}</td>
                        <td class="text-end tabular-nums">{{ number_format($row['hotels']) }}</td>
                        <td class="text-end tabular-nums">{{ $row['bookable_budget'] }}</td>
                        <td class="text-end tabular-nums">{{ $row['bookable_premium'] }}</td>
                        <td class="text-end tabular-nums">{{ $row['bookable_luxury'] }}</td>
                        <td>
                            @if ($row['ready'])
                                <span class="inline-flex items-center gap-1 text-xs font-medium text-primary-800"><x-icon name="check" class="size-4" /> Ready</span>
                            @else
                                <span class="text-xs text-slate-500">Not yet</span>
                            @endif
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </section>
</x-admin-layout>
