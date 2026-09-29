<x-admin-layout title="Vehicles">
    <x-admin.header title="Vehicles" subtitle="Fleet types per tier and group size (SRS 5.2 / 5.5). Rates are in USD.">
        @can('create', App\Models\Vehicle::class)
            <a href="{{ route('admin.vehicles.create') }}" class="btn btn-primary">Add vehicle</a>
        @endcan
    </x-admin.header>

    <div class="space-y-6">
        @forelse ($vehicles as $tier => $group)
            <section class="admin-card overflow-x-auto">
                <h3 class="border-b border-slate-200 px-4 py-3 font-semibold">{{ ucfirst($tier) }}</h3>
                <table class="admin-table">
                    <thead><tr><th>Type</th><th>Example</th><th>Travellers</th><th>Luggage</th><th class="text-end">Day rate</th><th class="text-end">Per km</th><th><span class="sr-only">Actions</span></th></tr></thead>
                    <tbody class="divide-y divide-slate-100">
                        @foreach ($group as $vehicle)
                            <tr>
                                <td class="font-semibold text-slate-900">{{ $vehicle->type }}</td>
                                <td class="text-xs">{{ $vehicle->example_model }}</td>
                                <td>{{ $vehicle->min_pax }}–{{ $vehicle->max_pax }}</td>
                                <td>{{ $vehicle->luggage_capacity ?? '—' }}</td>
                                <td class="text-end">${{ $vehicle->day_rate }}</td>
                                <td class="text-end">${{ $vehicle->km_rate }}</td>
                                <td class="space-x-3 text-end">
                                    <a href="{{ route('admin.vehicles.edit', $vehicle) }}" class="text-sm font-medium text-primary-700 hover:underline">@can('update', $vehicle) Edit @else View @endcan</a>
                                    @can('delete', $vehicle)<x-admin.delete-form :action="route('admin.vehicles.destroy', $vehicle)" />@endcan
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </section>
        @empty
            <div class="admin-card"><x-admin.empty message="No vehicles yet." /></div>
        @endforelse
    </div>
</x-admin-layout>
