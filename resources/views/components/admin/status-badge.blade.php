{{-- Coloured pill for draft/published/rejected and other statuses. --}}
@props(['status'])

@php
    $value = $status instanceof \BackedEnum ? $status->value : (string) $status;
    $classes = match ($value) {
        'published', 'approved', 'confirmed', 'completed', 'success', 'updated' => 'bg-primary-50 text-primary-800 ring-primary-200',
        'draft', 'submitted', 'info', 'skipped' => 'bg-slate-100 text-slate-700 ring-slate-200',
        'under_review', 'in_progress', 'needs_review', 'pending' => 'bg-accent-50 text-accent-700 ring-accent-200',
        'rejected', 'cancelled', 'failed', 'not_found', 'inactive' => 'bg-red-50 text-red-700 ring-red-200',
        default => 'bg-slate-100 text-slate-700 ring-slate-200',
    };
@endphp

<span {{ $attributes->class(['inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium ring-1 ring-inset whitespace-nowrap', $classes]) }}>
    {{ str($value)->replace('_', ' ')->ucfirst() }}
</span>
