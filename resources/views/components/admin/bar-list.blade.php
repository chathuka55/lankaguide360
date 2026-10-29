{{--
    Horizontal bars with direct value labels (ranking / breakdown), one series.
    <x-admin.bar-list :rows="[['label' => 'Kandy', 'value' => 12, 'display' => '12 trips', 'note' => '…']]" />
--}}
@props(['rows', 'empty' => 'No data for this period.'])

@php
    $max = max(1, collect($rows)->max('value') ?? 0);
@endphp

@if (collect($rows)->sum('value') == 0)
    <p class="py-6 text-center text-sm text-slate-500">{{ $empty }}</p>
@else
    <ul {{ $attributes->class('space-y-2') }} role="list">
        @foreach ($rows as $row)
            <li class="grid grid-cols-[minmax(0,10rem)_1fr] items-center gap-3 text-sm sm:grid-cols-[minmax(0,12rem)_1fr]"
                title="{{ $row['label'] }}: {{ $row['display'] ?? $row['value'] }}">
                <span class="truncate text-slate-700">{{ $row['label'] }}@if (! empty($row['note']))<span class="block truncate text-xs text-slate-400">{{ $row['note'] }}</span>@endif</span>
                <span class="flex min-w-0 items-center gap-2">
                    <span class="h-3 min-w-0 flex-1" aria-hidden="true">
                        <span class="block h-full rounded-e bg-primary-700" style="width: {{ $row['value'] > 0 ? max(1, $row['value'] / $max * 100) : 0 }}%"></span>
                    </span>
                    <span class="w-28 shrink-0 text-end tabular-nums text-slate-900">{{ $row['display'] ?? $row['value'] }}</span>
                </span>
            </li>
        @endforeach
    </ul>
@endif
