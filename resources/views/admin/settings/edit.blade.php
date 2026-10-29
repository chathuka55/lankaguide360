<x-admin-layout title="Settings">
    <x-admin.header title="Settings" subtitle="Prices and limits the Trip Builder uses. Seeded values are placeholders: set the real ones before launch." />

    <form method="POST" action="{{ route('admin.settings.update') }}" class="space-y-6">
        @csrf
        @method('PUT')
        @foreach ($groups as $group => $fields)
            <section class="admin-card p-5">
                <h3 class="mb-4 text-lg font-semibold">{{ $group }}</h3>
                <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                    @foreach ($fields as $key => [$label, $help])
                        <x-admin.input name="settings[{{ $key }}]" :label="$label" :value="$values[$key] ?? null" :help="$help" :required="true" />
                    @endforeach
                </div>
            </section>
        @endforeach
        <div class="flex justify-end"><button type="submit" class="btn btn-primary">Save settings</button></div>
    </form>

    <section class="admin-card mt-6 p-5" aria-labelledby="mail-heading">
        <h3 id="mail-heading" class="font-semibold">Email</h3>
        <p class="mt-1 text-sm text-slate-600">
            Sending with <strong>{{ config('mail.default') }}</strong>@if (config('mail.default') === 'smtp') via {{ config('mail.mailers.smtp.host') }}:{{ config('mail.mailers.smtp.port') }}@endif,
            from {{ config('mail.from.address') }}.
            @if (config('mail.default') === 'log') Emails are only written to the log file until SMTP details are added to <code>.env</code> (see docs/EMAIL.md). @endif
        </p>
        <form method="POST" action="{{ route('admin.settings.test-email') }}" class="mt-3 flex flex-col gap-2 sm:flex-row sm:items-end">
            @csrf
            <div class="flex-1">
                <label for="test-email" class="form-label">Send a test email to</label>
                <input type="email" id="test-email" name="email" value="{{ old('email', auth()->user()->email) }}" required class="form-control">
            </div>
            <button type="submit" class="btn btn-secondary">Send test email</button>
        </form>
    </section>
</x-admin-layout>
