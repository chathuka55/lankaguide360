{{-- Success / warning / error flash messages and a validation summary. --}}
@if (session('status'))
    <div class="mb-6 flex items-start gap-2 rounded-lg border border-primary-200 bg-primary-50 px-4 py-3 text-sm text-primary-800" role="status">
        <x-icon name="check" class="mt-0.5 size-4" />
        <span>{{ session('status') }}</span>
    </div>
@endif

@if (session('warning'))
    <div class="mb-6 rounded-lg border border-accent-300 bg-accent-50 px-4 py-3 text-sm text-accent-700" role="status">
        {!! nl2br(e(session('warning'))) !!}
    </div>
@endif

@if (session('error'))
    <div class="mb-6 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700" role="alert">
        {{ session('error') }}
    </div>
@endif

@if ($errors->any())
    <div class="mb-6 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700" role="alert">
        Please fix the {{ Str::plural('error', $errors->count()) }} highlighted below.
    </div>
@endif
