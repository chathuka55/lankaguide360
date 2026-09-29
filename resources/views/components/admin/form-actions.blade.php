{{-- Save/cancel row. Hidden for users who can't edit (agents see forms read-only). --}}
@props(['cancel', 'can' => true, 'label' => 'Save'])

<div class="flex flex-wrap items-center justify-end gap-2 border-t border-slate-200 pt-4">
    <a href="{{ $cancel }}" class="btn btn-secondary">{{ $can ? 'Cancel' : 'Back' }}</a>
    @if ($can)
        <button type="submit" class="btn btn-primary">{{ $label }}</button>
    @endif
</div>
