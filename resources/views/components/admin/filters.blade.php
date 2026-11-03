{{-- GET filter bar: search box + extra filters in the slot. Keeps other query params out.
     Fields wrap onto more rows when space runs out (iPad, small laptops), never squeezing the search box. --}}
@props(['action', 'placeholder' => 'Search…'])

<form method="GET" action="{{ $action }}" class="admin-card mb-4 flex flex-wrap items-end gap-3 p-4">
    <div class="min-w-0 flex-[2_1_16rem]">
        <label for="filter-q" class="form-label">Search</label>
        <input type="search" name="q" id="filter-q" value="{{ request('q') }}" placeholder="{{ $placeholder }}" class="form-control">
    </div>
    {{ $slot }}
    <div class="flex shrink-0 gap-2">
        <button type="submit" class="btn btn-primary">Filter</button>
        @if (collect(request()->query())->except('page')->filter(fn ($v) => filled($v))->isNotEmpty())
            <a href="{{ $action }}" class="btn btn-secondary">Reset</a>
        @endif
    </div>
</form>
