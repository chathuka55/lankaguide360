{{-- One photo with credit, license, status and admin actions. --}}
@props(['media', 'sortable' => false, 'bulkForm' => null, 'showOwner' => false])

@php
    $owner = $media->mediable;
    $ownerUrl = match ($media->mediable_type) {
        'place' => $owner ? route('admin.places.edit', $owner) : null,
        'hotel' => $owner ? route('admin.hotels.edit', $owner) : null,
        default => null,
    };
@endphp

<figure data-media-id="{{ $media->id }}" class="admin-card flex flex-col overflow-hidden">
    <div class="relative aspect-[4/3] bg-slate-100">
        <img src="{{ $media->url(400) }}" alt="{{ $media->alt }}" loading="lazy" width="400" height="300" class="size-full object-cover">
        <div class="absolute inset-x-2 top-2 flex items-center justify-between gap-2">
            <span class="flex items-center gap-1">
                @if ($bulkForm)
                    <input type="checkbox" name="ids[]" value="{{ $media->id }}" form="{{ $bulkForm }}" data-bulk-item class="form-check size-5 bg-white" aria-label="Select photo {{ $media->id }}">
                @endif
                @if ($media->is_cover)
                    <span class="rounded-full bg-accent-500 px-2 py-0.5 text-xs font-bold text-slate-900">Cover</span>
                @endif
            </span>
            <span class="flex items-center gap-1">
                <x-admin.status-badge :status="$media->status" class="bg-white/90" />
                @if ($sortable)
                    <button type="button" data-drag-handle class="cursor-grab rounded-md bg-white/90 p-1 text-slate-600 hover:text-slate-900" aria-label="Drag to reorder">
                        <x-icon name="menu" class="size-4" />
                    </button>
                @endif
            </span>
        </div>
    </div>

    <figcaption class="flex flex-1 flex-col gap-2 p-3 text-xs text-slate-600">
        @if ($showOwner && $owner)
            <a href="{{ $ownerUrl }}" class="text-sm font-semibold text-slate-900 hover:underline">{{ $owner->name }}</a>
        @endif
        <p>
            {{ $media->credit() ?? 'No credit recorded' }}
            @if ($media->license_url)
                · <a href="{{ $media->license_url }}" target="_blank" rel="noopener" class="text-primary-700 hover:underline">license</a>
            @endif
            @if ($media->source_url)
                · <a href="{{ $media->source_url }}" target="_blank" rel="noopener" class="text-primary-700 hover:underline">source</a>
            @endif
        </p>

        <details class="group">
            <summary class="cursor-pointer font-medium text-primary-700">Edit alt text &amp; status</summary>
            <form method="POST" action="{{ route('admin.media.update', $media) }}" class="mt-2 space-y-2">
                @csrf
                @method('PATCH')
                <label class="sr-only" for="alt-{{ $media->id }}">Alt text</label>
                <textarea id="alt-{{ $media->id }}" name="alt" rows="2" required maxlength="250" class="form-control text-xs">{{ $media->alt }}</textarea>
                <label class="sr-only" for="caption-{{ $media->id }}">Caption</label>
                <input id="caption-{{ $media->id }}" name="caption" value="{{ $media->caption }}" maxlength="500" placeholder="Caption (optional)" class="form-control text-xs">
                <div class="flex items-center gap-2">
                    <label class="sr-only" for="status-{{ $media->id }}">Status</label>
                    <select id="status-{{ $media->id }}" name="status" class="form-control py-1 text-xs">
                        <option value="published" @selected($media->status->value === 'published')>Published</option>
                        <option value="draft" @selected($media->status->value === 'draft')>Draft</option>
                    </select>
                    <button type="submit" class="btn btn-secondary btn-sm">Save</button>
                </div>
            </form>
        </details>

        <div class="mt-auto flex flex-wrap items-center gap-3 border-t border-slate-100 pt-2">
            @if ($media->status->value === 'draft')
                <form method="POST" action="{{ route('admin.media.bulk') }}">
                    @csrf
                    <input type="hidden" name="action" value="publish">
                    <input type="hidden" name="ids[]" value="{{ $media->id }}">
                    <button type="submit" class="font-semibold text-primary-700 hover:underline">Publish</button>
                </form>
            @endif
            @unless ($media->is_cover)
                <form method="POST" action="{{ route('admin.media.cover', $media) }}">
                    @csrf
                    <button type="submit" class="font-medium text-slate-700 hover:underline">Make cover</button>
                </form>
            @endunless
            <x-admin.delete-form :action="route('admin.media.destroy', $media)" label="Reject" confirm="Remove this photo? Commons photos won't be imported again." class="ms-auto" button="btn-link-danger text-xs font-medium" />
        </div>
    </figcaption>
</figure>
