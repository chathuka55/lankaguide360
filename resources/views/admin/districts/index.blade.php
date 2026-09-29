<x-admin-layout title="Districts">
    <x-admin.header title="Districts" subtitle="Sri Lanka's 25 districts. Categories linked here decide which districts the Trip Builder shows for each interest." />

    <div class="admin-card overflow-x-auto">
        <table class="admin-table">
            <thead><tr><th>District</th><th>Province</th><th>Categories</th><th class="text-end">Places</th><th class="text-end">Hotels</th><th><span class="sr-only">Actions</span></th></tr></thead>
            <tbody class="divide-y divide-slate-100">
                @foreach ($districts as $district)
                    <tr>
                        <td class="font-semibold text-slate-900">{{ $district->name }}</td>
                        <td>{{ $district->province->name }}</td>
                        <td class="text-xs">{{ $district->categories->pluck('name')->implode(', ') ?: '—' }}</td>
                        <td class="text-end">{{ $district->places_count }}</td>
                        <td class="text-end">{{ $district->hotels_count }}</td>
                        <td class="text-end"><a href="{{ route('admin.districts.edit', $district) }}" class="text-sm font-medium text-primary-700 hover:underline">@can('update', $district) Edit @else View @endcan</a></td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</x-admin-layout>
