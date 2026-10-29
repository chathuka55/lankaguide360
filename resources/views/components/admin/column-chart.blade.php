{{--
    Single-series column chart (one value per period), plain HTML/CSS.
    <x-admin.column-chart :rows="$rows" label-key="label" value-key="requests" :format="fn ($v) => number_format($v)" title="Trip requests" />
    Marks: 4px rounded data end on the baseline, 2px gap, recessive grid; hover/focus shows the
    exact value; "View as table" gives the same numbers without the chart.
--}}
@props(['rows', 'labelKey' => 'label', 'valueKey', 'title', 'format' => null, 'unit' => ''])

@php
    $format ??= fn ($v) => number_format((float) $v);
    $values = collect($rows)->map(fn ($r) => (float) $r[$valueKey]);
    $max = max(1, $values->max() ?? 0);
    // A round top for the axis: 1, 2 or 5 × 10^n.
    $magnitude = 10 ** floor(log10($max));
    $top = collect([1, 2, 5, 10])->map(fn ($m) => $m * $magnitude)->first(fn ($v) => $v >= $max);
    $ticks = [$top, $top / 2, 0];
    $every = max(1, (int) ceil(count($rows) / 12));
@endphp

<figure {{ $attributes->class('min-w-0') }}>
    <div class="flex gap-2">
        {{-- Y axis --}}
        <div class="flex h-48 flex-col justify-between py-0 text-end text-[11px] leading-none text-slate-500" aria-hidden="true">
            @foreach ($ticks as $tick)
                <span>{{ $format($tick) }}</span>
            @endforeach
        </div>
        {{-- Plot --}}
        <div class="relative h-48 flex-1">
            <div class="pointer-events-none absolute inset-0 flex flex-col justify-between" aria-hidden="true">
                @foreach ($ticks as $tick)
                    <div @class(['border-t', 'border-slate-300' => $tick == 0, 'border-dashed border-slate-200' => $tick != 0])></div>
                @endforeach
            </div>
            <ul class="relative flex h-full items-end gap-0.5" role="list" aria-label="{{ $title }}">
                @foreach ($rows as $row)
                    @php $value = (float) $row[$valueKey]; @endphp
                    <li class="group relative flex h-full flex-1 items-end" tabindex="0"
                        aria-label="{{ $row[$labelKey] }}: {{ $format($value) }}{{ $unit }}">
                        <span class="block w-full rounded-t bg-primary-700 transition group-hover:bg-primary-800 group-focus:bg-primary-800"
                              style="height: {{ $value > 0 ? max(1.5, $value / $top * 100) : 0 }}%"></span>
                        <span class="pointer-events-none absolute bottom-full left-1/2 z-10 mb-1 hidden -translate-x-1/2 whitespace-nowrap rounded-md bg-slate-900 px-2 py-1 text-xs text-white shadow-lg group-hover:block group-focus:block">
                            {{ $row[$labelKey] }}<br><strong>{{ $format($value) }}{{ $unit }}</strong>
                        </span>
                    </li>
                @endforeach
            </ul>
        </div>
    </div>
    {{-- X axis --}}
    <div class="ms-10 mt-1 flex gap-0.5 text-[11px] text-slate-500" aria-hidden="true">
        @foreach ($rows as $i => $row)
            <span class="flex-1 truncate text-center">{{ $i % $every === 0 ? \Illuminate\Support\Str::before($row[$labelKey], ' ') : '' }}</span>
        @endforeach
    </div>
    <details class="mt-2 text-xs">
        <summary class="cursor-pointer text-slate-500 hover:text-slate-700">View as table</summary>
        <table class="mt-2 w-full">
            <thead><tr class="text-slate-500"><th class="text-start font-medium">Period</th><th class="text-end font-medium">{{ $title }}</th></tr></thead>
            <tbody>
                @foreach ($rows as $row)
                    <tr class="border-t border-slate-100"><td class="py-0.5">{{ $row[$labelKey] }}</td><td class="text-end">{{ $format($row[$valueKey]) }}{{ $unit }}</td></tr>
                @endforeach
            </tbody>
        </table>
    </details>
</figure>
