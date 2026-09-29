{{-- Shown to agents on master-data forms. --}}
@props(['can' => true])

@unless ($can)
    <div class="mb-4 rounded-lg border border-slate-200 bg-slate-50 px-4 py-3 text-sm text-slate-600">
        Read-only: only admins can change master data.
    </div>
@endunless
