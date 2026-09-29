<x-admin-layout title="Trip requests">
    <x-admin.header title="Trip requests" subtitle="Submitted trip plans. Oldest new requests first; open one to review, edit and approve it." />

    <nav class="mb-4 flex gap-1 overflow-x-auto border-b border-slate-200" aria-label="Status">
        @foreach (App\Http\Controllers\Admin\TripRequestController::TABS as $key => $label)
            <a href="{{ route('admin.trips.index', ['tab' => $key]) }}"
               @class(['-mb-px whitespace-nowrap border-b-2 px-3 py-2 text-sm font-medium', 'border-primary-700 text-primary-800' => $tab === $key, 'border-transparent text-slate-600 hover:text-slate-900' => $tab !== $key])
               @if ($tab === $key) aria-current="page" @endif>
                {{ $label }}
                <span class="ms-1 rounded-full bg-slate-100 px-1.5 text-xs text-slate-600">{{ $counts[$key] ?? 0 }}</span>
            </a>
        @endforeach
    </nav>

    <x-admin.filters :action="route('admin.trips.index')" placeholder="Reference, name or email">
        <input type="hidden" name="tab" value="{{ $tab }}">
        <x-admin.filter-select name="tier" label="Tier" :options="App\Http\Controllers\Admin\TripRequestController::tierOptions()" placeholder="All" />
        <div>
            <label for="filter-from" class="form-label">Starts from</label>
            <input type="date" id="filter-from" name="from" value="{{ request('from') }}" class="form-control">
        </div>
        <div>
            <label for="filter-to" class="form-label">Starts before</label>
            <input type="date" id="filter-to" name="to" value="{{ request('to') }}" class="form-control">
        </div>
    </x-admin.filters>

    <div class="admin-card overflow-x-auto">
        <table class="admin-table">
            <thead>
                <tr><th>Reference</th><th>Traveller</th><th>Trip</th><th>Price</th><th>Agent</th><th>Submitted</th><th><span class="sr-only">Actions</span></th></tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @forelse ($trips as $trip)
                    <tr>
                        <td>
                            <a href="{{ route('admin.trips.show', $trip) }}" class="font-mono text-xs font-semibold text-primary-700 hover:underline">{{ $trip->reference }}</a>
                            <div class="mt-1"><x-admin.status-badge :status="$trip->status" /></div>
                        </td>
                        <td>
                            {{ $trip->contactName() }}
                            <div class="text-xs text-slate-500">{{ $trip->leadTraveller?->country }}@if ($trip->user) · registered @else · guest @endif</div>
                        </td>
                        <td class="text-xs">
                            {{ $trip->days }} days · {{ $trip->tier->label() }}<br>
                            {{ $trip->start_date?->format('d M Y') }} · {{ $trip->travellerCount() }} pax
                        </td>
                        <td class="whitespace-nowrap">${{ number_format((float) $trip->displayTotal(), 2) }}<div class="text-xs text-slate-500">{{ $trip->final_total !== null ? 'final' : 'estimate' }}</div></td>
                        <td class="text-xs">{{ $trip->agent?->name ?? '—' }}</td>
                        <td class="text-xs">{{ $trip->submitted_at?->diffForHumans() }}
                            @if ($trip->unread_count)
                                <div class="mt-1 inline-flex items-center gap-1 rounded-full bg-accent-100 px-2 py-0.5 font-semibold text-accent-700"><x-icon name="chat" class="size-3" /> {{ $trip->unread_count }}</div>
                            @endif
                        </td>
                        <td class="text-end">
                            @if (! $trip->agent_id && in_array($trip->status, App\Enums\TripStatus::open(), true))
                                <form method="POST" action="{{ route('admin.trips.assign-to-me', $trip) }}">
                                    @csrf
                                    <button type="submit" class="btn btn-secondary btn-sm">Assign to me</button>
                                </form>
                            @else
                                <a href="{{ route('admin.trips.show', $trip) }}" class="btn btn-secondary btn-sm">Open</a>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="7"><x-admin.empty message="No trips in this list." /></td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="mt-4">{{ $trips->links() }}</div>
</x-admin-layout>
