<x-admin-layout title="Cuisines">
    <x-admin.header title="Cuisines" subtitle="Cuisine preferences travellers can pick in builder step 6 (FR-11)." />

    <div class="grid gap-6 lg:grid-cols-3">
        <div class="admin-card overflow-x-auto lg:col-span-2">
            <table class="admin-table">
                <thead><tr><th>Name</th><th class="text-end">Used in trips</th><th><span class="sr-only">Actions</span></th></tr></thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse ($cuisines as $cuisine)
                        <tr>
                            <td>
                                @can('update', $cuisine)
                                    <form method="POST" action="{{ route('admin.cuisines.update', $cuisine) }}" class="flex gap-2">
                                        @csrf
                                        @method('PUT')
                                        <label class="sr-only" for="cuisine-{{ $cuisine->id }}">Name</label>
                                        <input id="cuisine-{{ $cuisine->id }}" name="name" value="{{ $cuisine->name }}" required maxlength="60" class="form-control">
                                        <button type="submit" class="btn btn-secondary btn-sm">Rename</button>
                                    </form>
                                @else
                                    <span class="font-semibold text-slate-900">{{ $cuisine->name }}</span>
                                @endcan
                            </td>
                            <td class="text-end">{{ $cuisine->trips_count }}</td>
                            <td class="text-end">
                                @can('delete', $cuisine)
                                    <x-admin.delete-form :action="route('admin.cuisines.destroy', $cuisine)" :confirm="'Delete '.$cuisine->name.'?'" />
                                @endcan
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="3"><x-admin.empty /></td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @can('create', App\Models\Cuisine::class)
            <form method="POST" action="{{ route('admin.cuisines.store') }}" class="admin-card h-fit space-y-4 p-5">
                @csrf
                <h3 class="font-semibold">Add cuisine</h3>
                <x-admin.input name="name" label="Name" :required="true" maxlength="60" />
                <button type="submit" class="btn btn-primary">Add</button>
            </form>
        @endcan
    </div>
</x-admin-layout>
