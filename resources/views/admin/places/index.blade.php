<x-admin-layout title="Places">
    <x-admin.header title="Places" :subtitle="number_format($places->total()).' places. Only published, active places appear on the site and in the Trip Builder.'">
        @can('create', App\Models\Place::class)
            <a href="{{ route('admin.places.create') }}" class="btn btn-primary">Add place</a>
        @endcan
    </x-admin.header>

    <x-admin.filters :action="route('admin.places.index')" placeholder="Place name">
        <x-admin.filter-select name="district" label="District" :options="$districts" />
        <x-admin.filter-select name="category" label="Category" :options="$categories" />
        <x-admin.filter-select name="status" label="Status" :options="['published' => 'Published', 'draft' => 'Draft', 'rejected' => 'Rejected']" />
        <x-admin.filter-select name="source" label="Source" :options="['seed' => 'Starter list', 'osm' => 'OpenStreetMap', 'manual' => 'Added by admin']" />
        <div class="flex flex-col gap-1 text-sm lg:pb-2">
            <label class="inline-flex items-center gap-2"><input type="checkbox" name="gems" value="1" @checked(request()->boolean('gems')) class="form-check"> Hidden gems</label>
            <label class="inline-flex items-center gap-2"><input type="checkbox" name="no_coords" value="1" @checked(request()->boolean('no_coords')) class="form-check"> No coordinates</label>
        </div>
    </x-admin.filters>

    <div class="admin-card overflow-hidden" x-data="bulkSelect">
        @can('create', App\Models\Place::class)
            <x-admin.bulk-bar form="bulk-places" :action="route('admin.places.bulk')" :actions="['publish_with_photos' => 'Publish with photos', 'publish' => 'Publish (not photos)', 'unpublish' => 'Unpublish', 'reject' => 'Reject', 'restore' => 'Restore']" />
        @endcan

        <div class="overflow-x-auto">
            <table class="admin-table">
                <thead>
                    <tr>
                        @can('create', App\Models\Place::class)<th class="w-8"><span class="sr-only">Select</span></th>@endcan
                        <th>Place</th>
                        <th>District</th>
                        <th>Categories</th>
                        <th>Status</th>
                        <th class="text-end">Photos</th>
                        <th><span class="sr-only">Actions</span></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse ($places as $place)
                        <tr>
                            @can('create', App\Models\Place::class)
                                <td><input type="checkbox" name="ids[]" value="{{ $place->id }}" form="bulk-places" data-bulk-item class="form-check" aria-label="Select {{ $place->name }}"></td>
                            @endcan
                            <td>
                                <div class="flex items-center gap-3">
                                    @if ($place->cover)
                                        <img src="{{ $place->cover->url(400) }}" alt="" loading="lazy" width="48" height="36" class="h-9 w-12 rounded object-cover">
                                    @else
                                        <span class="grid h-9 w-12 place-items-center rounded bg-slate-100 text-slate-400"><x-icon name="photo" class="size-4" /></span>
                                    @endif
                                    <div>
                                        <a href="{{ route('admin.places.edit', $place) }}" class="font-semibold text-slate-900 hover:underline">{{ $place->name }}</a>
                                        <div class="flex flex-wrap gap-1 text-xs text-slate-500">
                                            @if ($place->is_hidden_gem)<span class="text-accent-700">Hidden gem</span>@endif
                                            @if ($place->lat === null)<span class="text-red-600">No coordinates</span>@endif
                                            <span>{{ $place->source === 'osm' ? 'OSM' : ($place->source === 'seed' ? 'Starter list' : 'Admin') }}</span>
                                        </div>
                                    </div>
                                </div>
                            </td>
                            <td>{{ $place->district->name }}</td>
                            <td class="text-xs">{{ $place->categories->pluck('name')->implode(', ') ?: '—' }}</td>
                            <td>
                                <x-admin.status-badge :status="$place->is_active ? $place->status : 'rejected'" />
                            </td>
                            <td class="text-end">{{ $place->media_count }}</td>
                            <td class="text-end">
                                <a href="{{ route('admin.places.edit', $place) }}" class="text-sm font-medium text-primary-700 hover:underline">
                                    @can('update', $place) Edit @else View @endcan
                                </a>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="7"><x-admin.empty message="No places match these filters." /></td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="mt-4">{{ $places->links() }}</div>
</x-admin-layout>
