<div class="rounded-2xl border border-slate-200 bg-white p-5 text-sm shadow-sm">
    <h2 class="font-semibold">Your trip so far</h2>
    <dl class="mt-3 space-y-2">
        <div class="flex justify-between gap-3"><dt class="text-slate-500">Style</dt><dd class="text-end font-medium">{{ $summary['tier'] ?? '—' }}</dd></div>
        <div class="flex justify-between gap-3"><dt class="text-slate-500">Interests</dt><dd class="text-end">{{ $summary['categories']->join(', ') ?: '—' }}</dd></div>
        <div class="flex justify-between gap-3"><dt class="text-slate-500">Districts</dt><dd class="text-end">{{ $summary['districts']->join(', ') ?: '—' }}</dd></div>
        <div class="flex justify-between gap-3"><dt class="text-slate-500">Places</dt><dd class="text-end">{{ $summary['places'] ?: '—' }}</dd></div>
        <div class="flex justify-between gap-3"><dt class="text-slate-500">Dates</dt><dd class="text-end">@if ($summary['start']){{ $summary['start']->format('j M Y') }}@if ($summary['days']) · {{ $summary['days'] }} days @endif @elseif ($summary['days']){{ $summary['days'] }} days @else — @endif</dd></div>
        <div class="flex justify-between gap-3"><dt class="text-slate-500">Travellers</dt><dd class="text-end">{{ $summary['travellers'] }}</dd></div>
    </dl>
    <div class="mt-4 border-t border-slate-200 pt-4">
        @if ($summary['total'])
            <p class="text-slate-500">{{ $summary['is_estimate_only'] ? 'Indicative, from' : 'Estimated total' }}</p>
            <p class="text-2xl font-extrabold">${{ number_format((float) $summary['total'], 2) }}</p>
            @if ($summary['per_person'])
                <p class="text-slate-500">${{ number_format((float) $summary['per_person'], 2) }} per person</p>
            @endif
            <p class="mt-2 text-xs text-slate-500">Estimate — final price confirmed by your agent.</p>
        @else
            <p class="text-slate-500">The price appears once you choose a style and your dates.</p>
        @endif
    </div>
</div>
