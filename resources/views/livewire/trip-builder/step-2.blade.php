<p class="text-slate-600">What would you like to see? Pick as many as you like: we'll highlight the districts that match.</p>

<div class="mt-6 flex flex-wrap gap-3" role="group" aria-label="Interests">
    @foreach ($categories as $category)
        @php $on = in_array($category->id, $categoryIds, true); @endphp
        <button type="button" wire:click="toggleCategory({{ $category->id }})" aria-pressed="{{ $on ? 'true' : 'false' }}"
                @class([
                    'inline-flex items-center gap-2 rounded-full border-2 px-5 py-3 text-sm font-semibold transition focus:outline-none focus-visible:ring-4 focus-visible:ring-primary-300',
                    'border-primary-700 bg-primary-700 text-white shadow' => $on,
                    'border-slate-200 bg-white text-slate-700 hover:border-primary-300' => ! $on,
                ])>
            @if ($on)<x-icon name="check" class="size-4" />@endif
            {{ $category->name }}
            <span @class(['rounded-full px-2 text-xs', 'bg-white/20' => $on, 'bg-slate-100 text-slate-500' => ! $on])>{{ $category->places_count }}</span>
        </button>
    @endforeach
</div>
@error('categoryIds')<p class="mt-3 text-sm text-red-600" role="alert">{{ $message }}</p>@enderror
