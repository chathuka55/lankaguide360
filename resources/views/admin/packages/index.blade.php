<x-admin-layout title="Packages">
    <x-admin.header title="Packages" subtitle="Ready-made trips shown on the Home page. Building them from the Trip Builder (Save as package) arrives in phase 12.">
        @can('create', App\Models\Package::class)
            <a href="{{ route('admin.packages.create') }}" class="btn btn-primary">Add package</a>
        @endcan
    </x-admin.header>

    <div class="admin-card overflow-x-auto">
        <table class="admin-table">
            <thead><tr><th>Package</th><th>Tier</th><th>Days</th><th class="text-end">From</th><th>Template trip</th><th>Featured</th><th><span class="sr-only">Actions</span></th></tr></thead>
            <tbody class="divide-y divide-slate-100">
                @forelse ($packages as $package)
                    <tr>
                        <td class="font-semibold text-slate-900">{{ $package->name }}</td>
                        <td>{{ $package->tier?->label() ?? '—' }}</td>
                        <td>{{ $package->days ?? '—' }}</td>
                        <td class="text-end">{{ $package->from_price ? '$'.$package->from_price : '—' }}</td>
                        <td class="font-mono text-xs">{{ $package->templateTrip?->reference }}</td>
                        <td>{{ $package->is_featured ? 'Yes' : 'No' }}</td>
                        <td class="space-x-3 text-end">
                            <a href="{{ route('admin.packages.edit', $package) }}" class="text-sm font-medium text-primary-700 hover:underline">@can('update', $package) Edit @else View @endcan</a>
                            @can('delete', $package)<x-admin.delete-form :action="route('admin.packages.destroy', $package)" />@endcan
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="7"><x-admin.empty message="No packages yet. Six starter packages are created in phase 12." /></td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</x-admin-layout>
