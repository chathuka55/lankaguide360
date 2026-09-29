<x-admin-layout title="Categories">
    <x-admin.header title="Categories" subtitle="Interest categories travellers pick in the Trip Builder.">
        @can('create', App\Models\Category::class)
            <a href="{{ route('admin.categories.create') }}" class="btn btn-primary">Add category</a>
        @endcan
    </x-admin.header>

    <div class="admin-card overflow-x-auto">
        <table class="admin-table">
            <thead><tr><th>Category</th><th>Slug</th><th>Icon</th><th class="text-end">Districts</th><th class="text-end">Places</th><th><span class="sr-only">Actions</span></th></tr></thead>
            <tbody class="divide-y divide-slate-100">
                @forelse ($categories as $category)
                    <tr>
                        <td class="font-semibold text-slate-900">{{ $category->name }}</td>
                        <td class="font-mono text-xs">{{ $category->slug }}</td>
                        <td class="text-xs">{{ $category->icon ?: '—' }}</td>
                        <td class="text-end">{{ $category->districts_count }}</td>
                        <td class="text-end">{{ $category->places_count }}</td>
                        <td class="space-x-3 text-end">
                            <a href="{{ route('admin.categories.edit', $category) }}" class="text-sm font-medium text-primary-700 hover:underline">@can('update', $category) Edit @else View @endcan</a>
                            @can('delete', $category)
                                <x-admin.delete-form :action="route('admin.categories.destroy', $category)" :confirm="'Delete the category '.$category->name.'?'" />
                            @endcan
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="6"><x-admin.empty /></td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</x-admin-layout>
