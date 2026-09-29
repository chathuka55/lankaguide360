<x-admin-layout title="Dashboard">
    <div class="space-y-8">
        <section>
            <h2 class="text-2xl font-bold text-slate-900">Welcome back, {{ str(Auth::user()->name)->before(' ') }}</h2>
            <p class="mt-1 text-sm text-slate-600">
                Signed in as <span class="font-semibold">{{ Auth::user()->role->label() }}</span>.
                @can('admin') You can manage all content, prices and users. @else You can review trip requests and read master data. @endcan
            </p>
        </section>

        @can('admin')
            <section aria-labelledby="awaiting-heading">
                <h3 id="awaiting-heading" class="mb-3 text-sm font-semibold uppercase tracking-wide text-slate-500">Waiting for review</h3>
                <div class="grid gap-4 sm:grid-cols-3">
                    @foreach (['places' => 'Draft places', 'hotels' => 'Draft hotels', 'photos' => 'Draft photos'] as $key => $label)
                        <a href="{{ route('admin.review.index', ['tab' => $key]) }}" class="admin-card block p-5 transition hover:ring-primary-300">
                            <p class="text-sm font-medium text-slate-500">{{ $label }}</p>
                            <p class="mt-2 text-3xl font-bold {{ $awaiting[$key] ? 'text-accent-600' : 'text-slate-900' }}">{{ number_format($awaiting[$key]) }}</p>
                        </a>
                    @endforeach
                </div>
            </section>
        @endcan

        <section aria-labelledby="content-heading">
            <h3 id="content-heading" class="mb-3 text-sm font-semibold uppercase tracking-wide text-slate-500">Content</h3>
            <dl class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                <div class="admin-card p-5"><dt class="text-sm text-slate-500">Places</dt><dd class="mt-2 text-3xl font-bold">{{ number_format($totals['places']) }}</dd><dd class="text-xs text-slate-500">{{ number_format($totals['published_places']) }} published</dd></div>
                <div class="admin-card p-5"><dt class="text-sm text-slate-500">Hotels</dt><dd class="mt-2 text-3xl font-bold">{{ number_format($totals['hotels']) }}</dd><dd class="text-xs text-slate-500">{{ number_format($totals['published_hotels']) }} published</dd></div>
                @foreach (App\Enums\UserRole::cases() as $role)
                    @continue($role === App\Enums\UserRole::Admin)
                    <div class="admin-card p-5"><dt class="text-sm text-slate-500">{{ str($role->label())->plural() }}</dt><dd class="mt-2 text-3xl font-bold">{{ number_format($usersByRole[$role->value] ?? 0) }}</dd></div>
                @endforeach
            </dl>
        </section>

        <section aria-labelledby="trips-heading">
            <h3 id="trips-heading" class="mb-3 text-sm font-semibold uppercase tracking-wide text-slate-500">Trips by status</h3>
            <div class="admin-card flex flex-wrap gap-x-8 gap-y-3 p-5">
                @foreach ($tripsByStatus as $status => $total)
                    <a href="{{ route('admin.trips.index', ['tab' => $status]) }}" class="hover:opacity-80"><p class="text-2xl font-bold">{{ $total }}</p><x-admin.status-badge :status="$status" /></a>
                @endforeach
            </div>
        </section>

        <div class="grid gap-6 xl:grid-cols-2">
            <section class="admin-card p-5" aria-labelledby="latest-trips">
                <h3 id="latest-trips" class="font-semibold">Latest trip requests</h3>
                @if ($latestTrips->isEmpty())
                    <x-admin.empty message="No trip requests yet." />
                @else
                    <ul class="mt-3 divide-y divide-slate-100 text-sm">
                        @foreach ($latestTrips as $trip)
                            <li class="flex items-center justify-between gap-3 py-2">
                                <a href="{{ route('admin.trips.show', $trip) }}" class="hover:underline"><span class="font-mono text-xs">{{ $trip->reference }}</span> · {{ $trip->leadTraveller?->full_name ?? 'Draft' }} · {{ $trip->days }} days</a>
                                <x-admin.status-badge :status="$trip->status" />
                            </li>
                        @endforeach
                    </ul>
                @endif
            </section>

            @can('admin')
                <section class="admin-card p-5" aria-labelledby="latest-logs">
                    <div class="flex items-center justify-between">
                        <h3 id="latest-logs" class="font-semibold">Latest import activity</h3>
                        <a href="{{ route('admin.imports.index') }}" class="text-sm font-medium text-primary-700 hover:underline">Import tools</a>
                    </div>
                    @if ($latestLogs->isEmpty())
                        <x-admin.empty message="No imports yet." />
                    @else
                        <ul class="mt-3 divide-y divide-slate-100 text-xs">
                            @foreach ($latestLogs as $log)
                                <li class="py-2">
                                    <div class="flex items-center gap-2"><x-admin.status-badge :status="$log->status" /><span class="text-slate-500">{{ $log->importer }} · {{ $log->created_at?->diffForHumans() }}</span></div>
                                    <p class="mt-1 text-slate-600">{{ Str::limit($log->message, 160) }}</p>
                                </li>
                            @endforeach
                        </ul>
                    @endif
                </section>
            @endcan
        </div>
    </div>
</x-admin-layout>
