<x-admin-layout title="Import tools">
    <x-admin.header title="Import tools" subtitle="Fill the site from free, openly licensed sources. Every import lands as a draft for the review queue." />

    <div class="mb-6 grid gap-4 md:grid-cols-3">
        <div class="admin-card p-4">
            <p class="text-sm text-slate-500">Jobs waiting in the queue</p>
            <p class="mt-1 text-2xl font-bold">{{ $pendingJobs }}</p>
        </div>
        <div class="admin-card p-4">
            <p class="text-sm text-slate-500">Failed jobs</p>
            <p class="mt-1 text-2xl font-bold {{ $failedJobs ? 'text-red-600' : '' }}">{{ $failedJobs }}</p>
        </div>
        <div class="admin-card p-4 text-sm text-slate-600">
            Imports run in the background. Keep <code class="rounded bg-slate-100 px-1">php artisan queue:work</code> running in a terminal, or run the <code class="rounded bg-slate-100 px-1">lg:import-*</code> commands directly.
        </div>
    </div>

    <div class="mb-8 grid gap-4 lg:grid-cols-2 2xl:grid-cols-3">
        @foreach ($importers as $importer)
            @php($needsBoundaries = in_array($importer['name'], ['hotels', 'suggest-places'], true) && ! $hasBoundaries)
            <section class="admin-card flex flex-col p-5">
                <div class="flex items-start justify-between gap-2">
                    <div>
                        <h3 class="font-semibold text-slate-900">{{ $importer['label'] }}</h3>
                        <p class="text-xs text-slate-500">{{ $importer['source'] }} · <code>{{ $importer['command'] }}</code></p>
                    </div>
                </div>
                <p class="mt-2 text-sm text-slate-600">{{ $importer['description'] }}</p>
                <p class="mt-3 text-xs text-slate-500">
                    @if ($importer['last_run'])
                        Last run {{ $importer['last_run']->created_at?->diffForHumans() }}: {{ Str::after($importer['last_run']->message, ': ') }}
                    @else
                        Never run.
                    @endif
                </p>
                @if ($needsBoundaries)
                    <p class="mt-2 text-xs font-medium text-accent-700">Run “District boundaries” first.</p>
                @endif
                <form method="POST" action="{{ route('admin.imports.run') }}" class="mt-auto flex flex-wrap items-end gap-3 pt-4">
                    @csrf
                    <input type="hidden" name="importer" value="{{ $importer['name'] }}">
                    @if ($importer['name'] === 'images')
                        <label class="text-xs text-slate-600">Places (max)
                            <input type="number" name="limit" min="1" max="1000" placeholder="all" class="form-control mt-1 w-24 py-1">
                        </label>
                    @endif
                    <label class="inline-flex items-center gap-1.5 text-xs text-slate-600"><input type="checkbox" name="fresh" value="1" class="form-check"> Ignore cache</label>
                    <button type="submit" class="btn btn-primary btn-sm ms-auto" @disabled($needsBoundaries)>Run in background</button>
                </form>
            </section>
        @endforeach

        <section class="admin-card flex flex-col justify-between border-2 border-dashed border-primary-200 p-5">
            <div>
                <h3 class="font-semibold text-slate-900">Everything, in order</h3>
                <p class="mt-2 text-sm text-slate-600">Districts → descriptions → photos → hotels → suggestions. Takes a while because each source is rate-limited.</p>
            </div>
            <form method="POST" action="{{ route('admin.imports.run-all') }}" class="mt-4 flex items-center justify-end gap-3" onsubmit="return confirm('Queue every importer? This can take 30+ minutes.')">
                @csrf
                <label class="inline-flex items-center gap-1.5 text-xs text-slate-600"><input type="checkbox" name="fresh" value="1" class="form-check"> Ignore cache</label>
                <button type="submit" class="btn btn-secondary btn-sm">Run all</button>
            </form>
        </section>
    </div>

    <h3 class="mb-3 text-lg font-semibold">Import log</h3>
    <form method="GET" class="admin-card mb-4 flex flex-col gap-3 p-4 sm:flex-row sm:items-end">
        <x-admin.filter-select name="importer" label="Importer" :options="$logKeys" />
        <x-admin.filter-select name="status" label="Status" :options="$statuses" />
        <button type="submit" class="btn btn-primary">Filter</button>
    </form>

    <div class="admin-card overflow-x-auto">
        <table class="admin-table">
            <thead><tr><th>When</th><th>Importer</th><th>Status</th><th>Record</th><th>Message</th></tr></thead>
            <tbody class="divide-y divide-slate-100">
                @forelse ($logs as $log)
                    <tr>
                        <td class="text-xs whitespace-nowrap">{{ $log->created_at?->format('d M, H:i') }}</td>
                        <td class="text-xs">{{ $log->importer }}</td>
                        <td><x-admin.status-badge :status="$log->status" /></td>
                        <td class="text-xs">
                            @if ($log->target instanceof App\Models\Place)
                                <a href="{{ route('admin.places.edit', $log->target) }}" class="text-primary-700 hover:underline">{{ $log->target->name }}</a>
                            @elseif ($log->target instanceof App\Models\Hotel)
                                <a href="{{ route('admin.hotels.edit', $log->target) }}" class="text-primary-700 hover:underline">{{ $log->target->name }}</a>
                            @elseif ($log->target instanceof App\Models\District)
                                <a href="{{ route('admin.districts.edit', $log->target) }}" class="text-primary-700 hover:underline">{{ $log->target->name }}</a>
                            @else
                                —
                            @endif
                        </td>
                        <td class="max-w-xl text-xs">{{ $log->message }}</td>
                    </tr>
                @empty
                    <tr><td colspan="5"><x-admin.empty message="No imports have run yet." /></td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="mt-4">{{ $logs->links() }}</div>
</x-admin-layout>
