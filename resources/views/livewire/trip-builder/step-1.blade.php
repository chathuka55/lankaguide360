<p class="text-slate-600">Your style sets sensible defaults for hotels, meals, vehicle and guide. You can change any of them later.</p>

<div class="mt-6 grid gap-4 md:grid-cols-3" role="radiogroup" aria-label="Travel style">
    @foreach ($tiers as $option)
        @php $selected = $tier === $option['tier']->value; @endphp
        <button type="button" role="radio" aria-checked="{{ $selected ? 'true' : 'false' }}" wire:click="selectTier('{{ $option['tier']->value }}')"
                @class([
                    'flex flex-col items-start gap-3 rounded-2xl border-2 bg-white p-6 text-start transition focus:outline-none focus-visible:ring-4 focus-visible:ring-primary-300',
                    'border-primary-700 shadow-lg ring-4 ring-primary-100' => $selected,
                    'border-slate-200 hover:border-primary-300 hover:shadow-md' => ! $selected,
                ])>
            <span @class(['grid size-12 place-items-center rounded-xl', 'bg-primary-700 text-white' => $selected, 'bg-primary-50 text-primary-700' => ! $selected])>
                <x-icon :name="$option['icon']" class="size-6" />
            </span>
            <span class="text-xl font-bold">{{ $option['tier']->label() }}</span>
            <span class="text-sm text-slate-600">{{ $option['text'] }}</span>
            @if ($option['from'])
                <span class="mt-auto pt-2 text-sm text-slate-500">from <span class="text-lg font-bold text-slate-900">${{ number_format((float) $option['from']) }}</span> per person per day</span>
            @endif
        </button>
    @endforeach
</div>
@error('tier')<p class="mt-3 text-sm text-red-600" role="alert">{{ $message }}</p>@enderror
