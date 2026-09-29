<x-admin-layout title="Review queue">
    <x-admin.header title="Review queue" subtitle="Imported drafts waiting for a decision. Nothing reaches the site until you publish it." />

    <nav class="mb-6 flex gap-1 border-b border-slate-200" aria-label="Review tabs">
        @foreach (['places' => 'Places', 'hotels' => 'Hotels', 'photos' => 'Photos'] as $key => $label)
            <a href="{{ route('admin.review.index', ['tab' => $key]) }}" @if ($tab === $key) aria-current="page" @endif
               class="-mb-px flex items-center gap-2 border-b-2 px-4 py-2 text-sm font-semibold {{ $tab === $key ? 'border-primary-700 text-primary-800' : 'border-transparent text-slate-500 hover:text-slate-800' }}">
                {{ $label }}
                <span class="rounded-full bg-slate-100 px-2 py-0.5 text-xs text-slate-600">{{ number_format($counts[$key]) }}</span>
            </a>
        @endforeach
    </nav>

    @if ($tab === 'places')
        @if ($attention->isNotEmpty())
            <details class="admin-card mb-6 p-5" open>
                <summary class="cursor-pointer font-semibold text-accent-700">{{ $attention->count() }} {{ Str::plural('place', $attention->count()) }} need a correct Wikipedia title</summary>
                <p class="mt-2 text-sm text-slate-600">The importer did not guess. Set the right title on the place, then run “Place descriptions” again from Import tools.</p>
                <ul class="mt-3 divide-y divide-slate-100 text-sm">
                    @foreach ($attention as $item)
                        <li class="flex flex-col gap-1 py-2 sm:flex-row sm:items-start sm:justify-between">
                            <div>
                                <a href="{{ route('admin.places.edit', $item['place']) }}" class="font-semibold text-slate-900 hover:underline">{{ $item['place']->name }}</a>
                                <p class="text-xs text-slate-600">{{ $item['log']->message }}</p>
                            </div>
                            <a href="{{ route('admin.places.edit', $item['place']) }}" class="btn btn-secondary btn-sm shrink-0">Fix title</a>
                        </li>
                    @endforeach
                </ul>
            </details>
        @endif

        <form method="GET" class="admin-card mb-4 flex flex-col gap-3 p-4 lg:flex-row lg:items-end">
            <input type="hidden" name="tab" value="places">
            <x-admin.filter-select name="district" label="District" :options="$districts" />
            <x-admin.filter-select name="source" label="Source" :options="['seed' => 'Starter list', 'osm' => 'OpenStreetMap suggestion']" />
            <x-admin.filter-select name="ready" label="Ready to publish" :options="['1' => 'Ready', '0' => 'Missing data']" />
            <button type="submit" class="btn btn-primary">Filter</button>
        </form>

        <div class="admin-card overflow-hidden" x-data="bulkSelect">
            <x-admin.bulk-bar form="bulk-review-places" :action="route('admin.places.bulk')" :actions="['publish_with_photos' => 'Publish with photos', 'publish' => 'Publish (not photos)', 'reject' => 'Reject']" />
            <div class="overflow-x-auto">
                <table class="admin-table">
                    <thead><tr><th class="w-8"><span class="sr-only">Select</span></th><th>Place</th><th>Source</th><th>Checks</th><th><span class="sr-only">Actions</span></th></tr></thead>
                    <tbody class="divide-y divide-slate-100">
                        @forelse ($places as $place)
                            @php($problems = $publishing->problems($place))
                            <tr>
                                <td><input type="checkbox" name="ids[]" value="{{ $place->id }}" form="bulk-review-places" data-bulk-item class="form-check" aria-label="Select {{ $place->name }}"></td>
                                <td>
                                    <div class="flex gap-3">
                                        @if ($place->cover)
                                            <img src="{{ $place->cover->url(400) }}" alt="" loading="lazy" width="96" height="72" class="h-16 w-24 shrink-0 rounded object-cover">
                                        @else
                                            <span class="grid h-16 w-24 shrink-0 place-items-center rounded bg-slate-100 text-slate-400"><x-icon name="photo" /></span>
                                        @endif
                                        <div class="min-w-0">
                                            <a href="{{ route('admin.places.edit', $place) }}" class="font-semibold text-slate-900 hover:underline">{{ $place->name }}</a>
                                            <div class="text-xs text-slate-500">{{ $place->district->name }} · {{ $place->categories->pluck('name')->implode(', ') ?: 'no category' }}</div>
                                            <p class="mt-1 line-clamp-2 max-w-xl text-xs text-slate-600">{{ $place->short_description ?: 'No description yet.' }}</p>
                                        </div>
                                    </div>
                                </td>
                                <td class="text-xs">
                                    @if ($place->source === 'osm')
                                        <a href="https://www.openstreetmap.org/{{ $place->osm_id }}" target="_blank" rel="noopener" class="text-primary-700 hover:underline">OpenStreetMap</a><br><span class="text-slate-500">ODbL</span>
                                    @else
                                        Starter list
                                    @endif
                                    @if ($place->wikipedia_url)
                                        <br><a href="{{ $place->wikipedia_url }}" target="_blank" rel="noopener" class="text-primary-700 hover:underline">Wikipedia</a> <span class="text-slate-500">CC BY-SA</span>
                                    @endif
                                </td>
                                <td class="text-xs">
                                    <ul class="space-y-0.5">
                                        <li class="{{ $place->lat !== null ? 'text-primary-700' : 'text-red-600' }}">{{ $place->lat !== null ? '✓' : '✗' }} Coordinates</li>
                                        <li class="{{ $place->categories->isNotEmpty() ? 'text-primary-700' : 'text-red-600' }}">{{ $place->categories->isNotEmpty() ? '✓' : '✗' }} Category</li>
                                        <li class="{{ $place->description ? 'text-primary-700' : 'text-accent-700' }}">{{ $place->description ? '✓' : '–' }} Description</li>
                                        <li class="text-slate-600">{{ $place->draft_photos_count }} draft {{ Str::plural('photo', $place->draft_photos_count) }}</li>
                                    </ul>
                                </td>
                                <td>
                                    <div class="flex flex-wrap justify-end gap-2">
                                        <form method="POST" action="{{ route('admin.places.bulk') }}">
                                            @csrf
                                            <input type="hidden" name="ids[]" value="{{ $place->id }}">
                                            <input type="hidden" name="action" value="publish_with_photos">
                                            <button type="submit" class="btn btn-primary btn-sm" @disabled($problems) @if ($problems) title="{{ implode(', ', $problems) }}" @endif>Publish</button>
                                        </form>
                                        <a href="{{ route('admin.places.edit', $place) }}" class="btn btn-secondary btn-sm">Edit</a>
                                        <form method="POST" action="{{ route('admin.places.bulk') }}" onsubmit="return confirm('Reject this place? It is hidden, not deleted.')">
                                            @csrf
                                            <input type="hidden" name="ids[]" value="{{ $place->id }}">
                                            <input type="hidden" name="action" value="reject">
                                            <button type="submit" class="btn btn-secondary btn-sm text-red-700">Reject</button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="5"><x-admin.empty message="No draft places. Everything has been reviewed." /></td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
        <div class="mt-4">{{ $places->links() }}</div>
    @endif

    @if ($tab === 'hotels')
        <form method="GET" class="admin-card mb-4 flex flex-col gap-3 p-4 lg:flex-row lg:items-end">
            <input type="hidden" name="tab" value="hotels">
            <div class="min-w-0 flex-1"><label for="rq-q" class="form-label">Search</label><input id="rq-q" type="search" name="q" value="{{ request('q') }}" placeholder="Hotel or town" class="form-control"></div>
            <x-admin.filter-select name="district" label="District" :options="$districts" />
            <x-admin.filter-select name="tier" label="Guessed tier" :options="['budget' => 'Budget', 'premium' => 'Premium', 'luxury' => 'Luxury']" />
            <x-admin.filter-select name="type" label="Type" :options="['hotel' => 'Hotel', 'guest_house' => 'Guest house', 'hostel' => 'Hostel', 'resort' => 'Resort', 'apartment' => 'Apartment']" />
            <button type="submit" class="btn btn-primary">Filter</button>
        </form>

        <div class="admin-card overflow-hidden" x-data="bulkSelect">
            <x-admin.bulk-bar form="bulk-review-hotels" :action="route('admin.hotels.bulk')" :actions="['publish' => 'Publish', 'reject' => 'Reject']" />
            <div class="overflow-x-auto">
                <table class="admin-table">
                    <thead><tr><th class="w-8"><span class="sr-only">Select</span></th><th>Hotel</th><th>Town</th><th>Guessed tier</th><th>Source</th><th><span class="sr-only">Actions</span></th></tr></thead>
                    <tbody class="divide-y divide-slate-100">
                        @forelse ($hotels as $hotel)
                            <tr>
                                <td><input type="checkbox" name="ids[]" value="{{ $hotel->id }}" form="bulk-review-hotels" data-bulk-item class="form-check" aria-label="Select {{ $hotel->name }}"></td>
                                <td>
                                    <a href="{{ route('admin.hotels.edit', $hotel) }}" class="font-semibold text-slate-900 hover:underline">{{ $hotel->name }}</a>
                                    <div class="text-xs text-slate-500">{{ str($hotel->type ?? 'hotel')->replace('_', ' ')->ucfirst() }}@if ($hotel->star_rating) · {{ $hotel->star_rating }}★@endif @if ($hotel->website) · <a href="{{ $hotel->website }}" target="_blank" rel="noopener nofollow" class="text-primary-700 hover:underline">website</a>@endif</div>
                                </td>
                                <td>{{ $hotel->town }}<div class="text-xs text-slate-500">{{ $hotel->district->name }}</div></td>
                                <td>{{ $hotel->tier->label() }}</td>
                                <td class="text-xs">
                                    @if ($hotel->osm_id)
                                        <a href="https://www.openstreetmap.org/{{ $hotel->osm_id }}" target="_blank" rel="noopener" class="text-primary-700 hover:underline">OpenStreetMap</a><br><span class="text-slate-500">ODbL</span>
                                    @else
                                        Admin
                                    @endif
                                </td>
                                <td>
                                    <div class="flex flex-wrap justify-end gap-2">
                                        <form method="POST" action="{{ route('admin.hotels.bulk') }}">
                                            @csrf
                                            <input type="hidden" name="ids[]" value="{{ $hotel->id }}">
                                            <input type="hidden" name="action" value="publish">
                                            <button type="submit" class="btn btn-primary btn-sm">Publish</button>
                                        </form>
                                        <a href="{{ route('admin.hotels.edit', $hotel) }}" class="btn btn-secondary btn-sm">Edit</a>
                                        <form method="POST" action="{{ route('admin.hotels.bulk') }}" onsubmit="return confirm('Reject this hotel? It is hidden, not deleted.')">
                                            @csrf
                                            <input type="hidden" name="ids[]" value="{{ $hotel->id }}">
                                            <input type="hidden" name="action" value="reject">
                                            <button type="submit" class="btn btn-secondary btn-sm text-red-700">Reject</button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="6"><x-admin.empty message="No draft hotels. Import them from Import tools." /></td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
        <div class="mt-4">{{ $hotels->links() }}</div>
    @endif

    @if ($tab === 'photos')
        <div x-data="bulkSelect">
            <div class="admin-card mb-4 overflow-hidden">
                <x-admin.bulk-bar form="bulk-review-photos" :action="route('admin.media.bulk')" :actions="['publish' => 'Publish', 'reject' => 'Reject']" />
                <p class="px-4 py-3 text-xs text-slate-600">Check each photo shows the right place and the credit is complete. Rejected Commons photos are never imported again.</p>
            </div>
            @if ($photos->isEmpty())
                <div class="admin-card"><x-admin.empty message="No draft photos." /></div>
            @else
                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3 2xl:grid-cols-4">
                    @foreach ($photos as $media)
                        <x-admin.photo-card :media="$media" bulk-form="bulk-review-photos" :show-owner="true" />
                    @endforeach
                </div>
            @endif
        </div>
        <div class="mt-4">{{ $photos->links() }}</div>
    @endif
</x-admin-layout>
