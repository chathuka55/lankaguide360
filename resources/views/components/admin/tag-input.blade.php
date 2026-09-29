{{-- Chips input submitted as name[]: <x-admin.tag-input name="amenities" :tags="$hotel->amenities" :suggestions="[...]" /> --}}
@props(['name', 'label', 'tags' => [], 'suggestions' => [], 'help' => null, 'readonly' => false])

@php($current = old($name, $tags ?? []))

<div x-data="tagInput({ tags: @js(array_values((array) $current)), suggestions: @js(array_values($suggestions)) })">
    <label for="f-{{ $name }}-draft" class="form-label">{{ $label }}</label>

    <template x-for="tag in tags" :key="tag">
        <input type="hidden" name="{{ $name }}[]" :value="tag">
    </template>

    <div class="flex flex-wrap items-center gap-2 rounded-lg border border-slate-300 bg-white p-2 focus-within:border-primary-500 focus-within:ring-1 focus-within:ring-primary-500">
        <template x-for="tag in tags" :key="'chip-' + tag">
            <span class="inline-flex items-center gap-1 rounded-full bg-primary-50 px-2.5 py-1 text-xs font-medium text-primary-800">
                <span x-text="label(tag)"></span>
                @unless ($readonly)
                    <button type="button" @click="remove(tag)" class="rounded-full hover:text-primary-950" :aria-label="'Remove ' + label(tag)">
                        <x-icon name="x" class="size-3.5" />
                    </button>
                @endunless
            </span>
        </template>
        @unless ($readonly)
            <input id="f-{{ $name }}-draft" type="text" x-model="draft" @keydown.enter.prevent="add()" @keydown.comma.prevent="add()"
                   placeholder="Type and press Enter" class="min-w-32 flex-1 border-0 p-1 text-sm focus:ring-0">
        @endunless
    </div>

    @unless ($readonly)
        <div class="mt-2 flex flex-wrap gap-1.5">
            <template x-for="option in suggestions.filter((s) => ! tags.includes(s))" :key="'s-' + option">
                <button type="button" @click="add(option)" class="rounded-full border border-dashed border-slate-300 px-2 py-0.5 text-xs text-slate-600 hover:border-primary-400 hover:text-primary-700">
                    + <span x-text="label(option)"></span>
                </button>
            </template>
        </div>
    @endunless

    @if ($help)
        <p class="mt-1 text-xs text-slate-500">{{ $help }}</p>
    @endif
    @error($name)<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
    @error($name.'.*')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
</div>
