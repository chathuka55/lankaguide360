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
</x-admin-layout>
