{{-- Marks a fictional demo hotel (php artisan lg:demo-hotels). --}}
@props(['hotel'])
@if ($hotel?->is_demo)
    <span {{ $attributes->class('inline-flex items-center rounded-full bg-slate-100 px-2 py-0.5 text-[11px] font-medium text-slate-600 ring-1 ring-inset ring-slate-200 align-middle') }} title="Fictional hotel for demonstrations. Real hotels and rates are added by the LankaGuide360 team.">Demo listing</span>
@endif
