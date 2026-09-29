@include('livewire.trip-builder.plan-notices')

{{-- Hotel filters --}}
<div class="flex flex-wrap items-center gap-2 text-sm">
    <span class="text-slate-500">Hotel filters:</span>
    @foreach (['kid_friendly' => 'Kid-friendly', 'pool' => 'Pool', 'beach_front' => 'Beach-front'] as $key => $label)
        <button type="button" wire:click="toggleHotelFilter('{{ $key }}')" aria-pressed="{{ $hotelFilters[$key] ? 'true' : 'false' }}"
                @class(['rounded-full px-3 py-1 font-medium', 'bg-primary-700 text-white' => $hotelFilters[$key], 'bg-slate-100 text-slate-700 hover:bg-slate-200' => ! $hotelFilters[$key]])>
            @if ($hotelFilters[$key])✓ @endif{{ $label }}
        </button>
    @endforeach
</div>

<div class="mt-5 space-y-6">
    @forelse ($nights as $night)
        <section wire:key="night-{{ $night['day'] }}" aria-labelledby="night-{{ $night['day'] }}-heading" class="rounded-2xl border border-slate-200 bg-white p-5">
            <div class="flex flex-wrap items-baseline justify-between gap-2">
                <h2 id="night-{{ $night['day'] }}-heading" class="font-semibold">Night {{ $night['day'] }} · {{ $night['town'] }}</h2>
                <p class="text-sm text-slate-500">{{ $night['date'] ? \Illuminate\Support\Carbon::parse($night['date'])->format('D j M') : '' }}</p>
            </div>

            @if ($night['options']->isEmpty())
                <p class="mt-3 rounded-lg bg-slate-50 p-3 text-sm text-slate-600">No published {{ strtolower($summary['tier'] ?? '') }} hotels near {{ $night['town'] }} yet. Your agent will choose one for you.</p>
            @else
                <div class="mt-3 grid gap-3 md:grid-cols-3">
                    @foreach ($night['options'] as $option)
                        @php $hotel = $option['hotel']; $on = $night['selected']?->is($hotel); @endphp
                        <button type="button" wire:click="chooseHotel({{ $night['day'] }}, {{ $hotel->id }})" wire:key="night-{{ $night['day'] }}-hotel-{{ $hotel->id }}" aria-pressed="{{ $on ? 'true' : 'false' }}"
                                @class(['flex flex-col overflow-hidden rounded-xl border-2 text-start transition', 'border-primary-700 ring-2 ring-primary-100' => $on, 'border-slate-200 hover:border-primary-300' => ! $on])>
                            <span class="block aspect-[16/9] w-full bg-primary-50">
                                @if ($hotel->cover)
                                    <x-media-img :media="$hotel->cover" :width="400" sizes="(min-width: 768px) 20vw, 100vw" :alt="$hotel->name" class="size-full object-cover" />
                                @endif
                            </span>
                            <span class="block p-3">
                                <span class="block font-semibold">{{ $hotel->name }}</span>
                                <span class="block text-xs text-slate-500">
                                    @if ($hotel->star_rating){{ str_repeat('★', $hotel->star_rating) }} · @endif{{ $hotel->town }}@if ($hotel->kid_friendly) · kid-friendly @endif
                                </span>
                                <span class="mt-1 block text-sm">
                                    @if ($option['rate'])
                                        <strong>${{ number_format((float) $option['rate']->price_per_night) }}</strong> <span class="text-slate-500">/ room / night · {{ $option['rate']->meal_plan->value }}</span>
                                        @if ($option['rate']->is_estimate)<span class="text-xs text-slate-400">(estimate)</span>@endif
                                    @else
                                        <span class="text-slate-500">Price on request</span>
                                    @endif
                                </span>
                            </span>
                        </button>
                    @endforeach
                </div>

                @if ($night['rates']->count() > 1)
                    <div class="mt-3 max-w-md">
                        <label for="rate-{{ $night['day'] }}" class="form-label">Room type</label>
                        <select id="rate-{{ $night['day'] }}" class="form-control" wire:change="chooseRate({{ $night['day'] }}, $event.target.value)">
                            @foreach ($night['rates'] as $rate)
                                <option value="{{ $rate->id }}" @selected($night['rate_id'] === $rate->id)>{{ $rate->room_type }} · {{ $rate->meal_plan->label() }} · ${{ number_format((float) $rate->price_per_night, 2) }}</option>
                            @endforeach
                        </select>
                    </div>
                @endif
            @endif
        </section>
    @empty
        <p class="rounded-xl bg-slate-50 p-4 text-sm text-slate-600">Your trip has no overnight stays.</p>
    @endforelse
</div>

<div class="mt-8 grid gap-6 md:grid-cols-2">
    <fieldset class="rounded-2xl border border-slate-200 bg-white p-5">
        <legend class="float-left w-full font-semibold">Meal plan</legend>
        <div class="clear-both space-y-2 pt-2">
            @foreach ($mealPlans as $plan)
                <label class="flex cursor-pointer items-center gap-3 rounded-lg p-2 hover:bg-slate-50">
                    <input type="radio" wire:model.live="mealPlan" value="{{ $plan->value }}" class="text-primary-700 focus:ring-primary-500">
                    <span>{{ $plan->label() }}</span>
                    @if ($plan === $defaultMealPlan)<span class="rounded-full bg-primary-50 px-2 text-xs text-primary-800">suggested</span>@endif
                </label>
            @endforeach
        </div>
        @error('mealPlan')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
    </fieldset>

    <div class="rounded-2xl border border-slate-200 bg-white p-5">
        <h2 class="font-semibold">Food you'd like to try</h2>
        <div class="mt-3 flex flex-wrap gap-2">
            @foreach ($cuisines as $cuisine)
                @php $on = in_array($cuisine->id, $cuisineIds, true); @endphp
                <button type="button" wire:click="toggleCuisine({{ $cuisine->id }})" aria-pressed="{{ $on ? 'true' : 'false' }}" wire:key="cuisine-{{ $cuisine->id }}"
                        @class(['rounded-full px-3 py-1 text-sm', 'bg-primary-700 text-white' => $on, 'bg-slate-100 text-slate-700 hover:bg-slate-200' => ! $on])>{{ $cuisine->name }}</button>
            @endforeach
        </div>
        <label for="dietaryNotes" class="form-label mt-4">Dietary needs or allergies</label>
        <textarea id="dietaryNotes" wire:model.blur="dietaryNotes" rows="2" maxlength="500" class="form-control" placeholder="e.g. vegetarian, nut allergy"></textarea>
        @error('dietaryNotes')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
    </div>
</div>
