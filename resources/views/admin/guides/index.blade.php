<x-admin-layout title="Guides">
    <x-admin.header title="Guides" subtitle="Chauffeur-guides, SLTDA national guides and site guides, filtered by language in the Trip Builder.">
        @can('create', App\Models\Guide::class)
            <a href="{{ route('admin.guides.create') }}" class="btn btn-primary">Add guide</a>
        @endcan
    </x-admin.header>

    <div class="admin-card overflow-x-auto">
        <table class="admin-table">
            <thead><tr><th>Name</th><th>Type</th><th>Languages</th><th class="text-end">Day rate</th><th>Available</th><th><span class="sr-only">Actions</span></th></tr></thead>
            <tbody class="divide-y divide-slate-100">
                @forelse ($guides as $guide)
                    <tr>
                        <td class="font-semibold text-slate-900">{{ $guide->name }}</td>
                        <td>{{ $guide->type->label() }}</td>
                        <td class="text-xs">{{ implode(', ', $guide->languages ?? []) }}</td>
                        <td class="text-end">${{ $guide->day_rate }}</td>
                        <td>{{ $guide->is_available ? 'Yes' : 'No' }}</td>
                        <td class="space-x-3 text-end">
                            <a href="{{ route('admin.guides.edit', $guide) }}" class="text-sm font-medium text-primary-700 hover:underline">@can('update', $guide) Edit @else View @endcan</a>
                            @can('delete', $guide)<x-admin.delete-form :action="route('admin.guides.destroy', $guide)" />@endcan
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="6"><x-admin.empty /></td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</x-admin-layout>
