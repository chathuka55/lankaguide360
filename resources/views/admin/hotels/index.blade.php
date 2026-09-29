<x-admin-layout title="Hotels">
    <x-admin.header title="Hotels" :subtitle="number_format($hotels->total()).' stays. Published hotels with room rates are suggested in the Trip Builder.'">
        @can('create', App\Models\Hotel::class)
            <a href="{{ route('admin.hotels.create') }}" class="btn btn-primary">Add hotel</a>
        @endcan
    </x-admin.header>

    <x-admin.filters :action="route('admin.hotels.index')" placeholder="Hotel or town">
        <x-admin.filter-select name="district" label="District" :options="$districts" />
        <x-admin.filter-select name="tier" label="Tier" :options="['budget' => 'Budget', 'premium' => 'Premium', 'luxury' => 'Luxury']" />
        <x-admin.filter-select name="type" label="Type" :options="array_combine(App\Http\Requests\Admin\HotelRequest::TYPES, array_map(fn ($t) => str($t)->replace('_', ' ')->ucfirst(), App\Http\Requests\Admin\HotelRequest::TYPES))" />
        <x-admin.filter-select name="status" label="Status" :options="['published' => 'Published', 'draft' => 'Draft', 'rejected' => 'Rejected']" />
        <label class="inline-flex items-center gap-2 text-sm lg:pb-2"><input type="checkbox" name="no_rates" value="1" @checked(request()->boolean('no_rates')) class="form-check"> No room rates</label>
    </x-admin.filters>

    <div class="admin-card overflow-hidden" x-data="bulkSelect">
        @can('create', App\Models\Hotel::class)
            <x-admin.bulk-bar form="bulk-hotels" :action="route('admin.hotels.bulk')" :actions="['publish' => 'Publish', 'publish_with_photos' => 'Publish with photos', 'unpublish' => 'Unpublish', 'reject' => 'Reject', 'restore' => 'Restore']" />
        @endcan

        <div class="overflow-x-auto">
            <table class="admin-table">
                <thead>
                    <tr>
                        @can('create', App\Models\Hotel::class)<th class="w-8"><span class="sr-only">Select</span></th>@endcan
                        <th>Hotel</th>
                        <th>Town</th>
                        <th>Tier</th>
                        <th>Status</th>
                        <th class="text-end">Rates</th>
                        <th class="text-end">Photos</th>
                        <th><span class="sr-only">Actions</span></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse ($hotels as $hotel)
                        <tr>
                            @can('create', App\Models\Hotel::class)
                                <td><input type="checkbox" name="ids[]" value="{{ $hotel->id }}" form="bulk-hotels" data-bulk-item class="form-check" aria-label="Select {{ $hotel->name }}"></td>
                            @endcan
                            <td>
                                <a href="{{ route('admin.hotels.edit', $hotel) }}" class="font-semibold text-slate-900 hover:underline">{{ $hotel->name }}</a>
                                <div class="text-xs text-slate-500">
                                    {{ str($hotel->type ?? 'hotel')->replace('_', ' ')->ucfirst() }}
                                    @if ($hotel->star_rating) · {{ $hotel->star_rating }}★ @endif
                                    @if ($hotel->kid_friendly) · Kid-friendly @endif
                                    @if ($hotel->osm_id) · OSM @endif
                                </div>
                            </td>
                            <td>{{ $hotel->town }}<div class="text-xs text-slate-500">{{ $hotel->district->name }}</div></td>
                            <td>{{ $hotel->tier->label() }}</td>
                            <td><x-admin.status-badge :status="$hotel->is_active ? $hotel->status : 'rejected'" /></td>
                            <td class="text-end">{{ $hotel->room_rates_count ?: '—' }}</td>
                            <td class="text-end">{{ $hotel->media_count ?: '—' }}</td>
                            <td class="text-end">
                                <a href="{{ route('admin.hotels.edit', $hotel) }}" class="text-sm font-medium text-primary-700 hover:underline">@can('update', $hotel) Edit @else View @endcan</a>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="8"><x-admin.empty message="No hotels match these filters. Import them with lg:import-hotels or add one." /></td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="mt-4">{{ $hotels->links() }}</div>
</x-admin-layout>
