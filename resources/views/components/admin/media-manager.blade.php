{{--
    Photo gallery + tools for a place/hotel edit page (admin only for changes).
    <x-admin.media-manager :owner="$place" />
--}}
@props(['owner'])

@php
    $type = $owner->getMorphClass();
    $photos = $owner->media;
    $canEdit = auth()->user()->can('update', $owner);
    $licenses = config('lankaguide.media_licenses');
@endphp

<section id="photos" class="admin-card scroll-mt-20 p-5" aria-labelledby="photos-heading">
    <div class="mb-4 flex flex-wrap items-center justify-between gap-2">
        <div>
            <h3 id="photos-heading" class="text-lg font-semibold">Photos</h3>
            <p class="text-sm text-slate-600">
                {{ $photos->count() }} {{ Str::plural('photo', $photos->count()) }}.
                Only published photos appear on the site. @if ($canEdit) Drag to reorder; the cover is shown first. @endif
            </p>
        </div>

        @if ($canEdit)
            <div class="flex flex-wrap gap-2">
                {{-- Commons search modal --}}
                <div x-data="commonsSearch({ url: @js(route('admin.media.commons-search')), query: @js($owner->name.' Sri Lanka') })">
                    <button type="button" class="btn btn-secondary btn-sm" @click="open = true; if (! results.length) search()">
                        <x-icon name="photo" class="size-4" /> Search Wikimedia Commons
                    </button>

                    <div x-show="open" x-cloak class="fixed inset-0 z-50 flex items-start justify-center overflow-y-auto bg-slate-900/60 p-4" @keydown.escape.window="open = false">
                        <div class="w-full max-w-5xl rounded-xl bg-white shadow-2xl" role="dialog" aria-modal="true" aria-labelledby="commons-title" @click.outside="open = false">
                            <div class="flex items-center justify-between border-b border-slate-200 px-5 py-3">
                                <h4 id="commons-title" class="font-semibold">Wikimedia Commons</h4>
                                <button type="button" @click="open = false" class="rounded p-1 text-slate-500 hover:text-slate-900"><span class="sr-only">Close</span><x-icon name="x" class="size-5" /></button>
                            </div>
                            <div class="space-y-4 p-5">
                                <form @submit.prevent="search()" class="flex gap-2">
                                    <label for="commons-q" class="sr-only">Search</label>
                                    <input id="commons-q" type="search" x-model="query" class="form-control">
                                    <button type="submit" class="btn btn-primary" :disabled="loading">Search</button>
                                </form>
                                <p class="text-xs text-slate-500">Only CC0, CC BY, CC BY-SA and public-domain photos at least {{ config('lankaguide.import.min_image_width') }} px wide can be imported. Credits are saved automatically.</p>
                                <p x-show="loading" class="text-sm text-slate-500">Searching…</p>
                                <p x-show="error" x-text="error" class="text-sm text-red-600"></p>

                                <form method="POST" action="{{ route('admin.media.commons', ['type' => $type, 'id' => $owner->getKey()]) }}">
                                    @csrf
                                    <template x-for="title in selected" :key="title">
                                        <input type="hidden" name="titles[]" :value="title">
                                    </template>
                                    <div class="grid max-h-[60vh] grid-cols-2 gap-3 overflow-y-auto sm:grid-cols-3 lg:grid-cols-4">
                                        <template x-for="photo in results" :key="photo.title">
                                            <label class="relative flex cursor-pointer flex-col overflow-hidden rounded-lg ring-1"
                                                   :class="! photo.usable ? 'cursor-not-allowed opacity-50 ring-slate-200' : (selected.includes(photo.title) ? 'ring-2 ring-primary-600' : 'ring-slate-200 hover:ring-primary-300')">
                                                <input type="checkbox" class="form-check absolute start-2 top-2 size-5 bg-white" :disabled="! photo.usable"
                                                       :checked="selected.includes(photo.title)" @change="toggle(photo.title)" :aria-label="photo.title">
                                                <img :src="photo.thumb" :alt="photo.description || photo.title" loading="lazy" class="aspect-[4/3] w-full object-cover">
                                                <span class="space-y-0.5 p-2 text-xs">
                                                    <span class="block truncate font-medium text-slate-900" x-text="photo.title.replace('File:', '')"></span>
                                                    <span class="block truncate text-slate-600" x-text="(photo.author || 'Unknown author') + ' · ' + (photo.license || 'no license')"></span>
                                                    <span class="block text-slate-500" x-text="photo.width + ' × ' + photo.height"></span>
                                                    <span x-show="photo.reason" class="block font-medium text-red-600" x-text="photo.reason"></span>
                                                    <a :href="photo.page" target="_blank" rel="noopener" class="text-primary-700 hover:underline" @click.stop>View on Commons</a>
                                                </span>
                                            </label>
                                        </template>
                                    </div>
                                    <div class="mt-4 flex items-center justify-end gap-2 border-t border-slate-200 pt-4">
                                        <span class="me-auto text-sm text-slate-600"><span x-text="selected.length"></span> selected (max 10)</span>
                                        <button type="button" class="btn btn-secondary" @click="open = false">Cancel</button>
                                        <button type="submit" class="btn btn-primary" :disabled="selected.length === 0 || selected.length > 10">Import selected</button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        @endif
    </div>

    @if ($photos->isEmpty())
        <x-admin.empty message="No photos yet." />
    @else
        <div x-data="sortableGallery({ url: @js($canEdit ? route('admin.media.reorder', ['type' => $type, 'id' => $owner->getKey()]) : null) })">
            <div x-ref="grid" class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-3">
                @foreach ($photos as $media)
                    @if ($canEdit)
                        <x-admin.photo-card :media="$media" :sortable="true" />
                    @else
                        <figure class="admin-card overflow-hidden">
                            <img src="{{ $media->url(400) }}" alt="{{ $media->alt }}" loading="lazy" width="400" height="300" class="aspect-[4/3] w-full object-cover">
                            <figcaption class="p-3 text-xs text-slate-600">{{ $media->credit() }}</figcaption>
                        </figure>
                    @endif
                @endforeach
            </div>
            <p x-show="message" x-text="message" class="mt-2 text-xs text-slate-500" role="status"></p>
        </div>
    @endif

    @if ($canEdit)
        <div class="mt-6 grid gap-4 lg:grid-cols-2">
            {{-- Upload --}}
            <details class="rounded-lg border border-slate-200 p-4" @if ($errors->has('photo')) open @endif>
                <summary class="cursor-pointer font-semibold text-slate-900">Upload a photo</summary>
                <form method="POST" action="{{ route('admin.media.upload', ['type' => $type, 'id' => $owner->getKey()]) }}" enctype="multipart/form-data" class="mt-4 space-y-3">
                    @csrf
                    <div>
                        <label for="f-photo" class="form-label">Photo (JPEG, PNG or WebP, at least 800 px wide, max 15 MB) <span class="text-red-600">*</span></label>
                        <input type="file" name="photo" id="f-photo" accept="image/jpeg,image/png,image/webp" required class="block w-full text-sm text-slate-600 file:me-3 file:rounded-lg file:border-0 file:bg-primary-50 file:px-3 file:py-2 file:font-semibold file:text-primary-800">
                        @error('photo')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                    </div>
                    @include('admin.partials.photo-rights-fields', ['prefix' => 'upload', 'licenses' => $licenses])
                    <button type="submit" class="btn btn-primary">Upload</button>
                </form>
            </details>

            {{-- Import from URL --}}
            <details class="rounded-lg border border-slate-200 p-4" @if ($errors->has('url')) open @endif>
                <summary class="cursor-pointer font-semibold text-slate-900">Import from a URL</summary>
                <form method="POST" action="{{ route('admin.media.url', ['type' => $type, 'id' => $owner->getKey()]) }}" class="mt-4 space-y-3">
                    @csrf
                    <x-admin.input name="url" label="Image URL" type="url" :required="true" placeholder="https://…" help="Only use images you own or have written permission to use." />
                    @include('admin.partials.photo-rights-fields', ['prefix' => 'url', 'licenses' => $licenses])
                    <button type="submit" class="btn btn-primary">Import</button>
                </form>
            </details>
        </div>
    @endif
</section>
