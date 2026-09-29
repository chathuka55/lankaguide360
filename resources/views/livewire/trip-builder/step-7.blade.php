<section aria-labelledby="vehicle-heading">
    <h2 id="vehicle-heading" class="font-semibold">Vehicle with driver</h2>
    <p class="mt-1 text-sm text-slate-600">
        Suggested for {{ $summary['travellers'] }} {{ Str::plural('traveller', $summary['travellers']) }}@if ($luggage !== null) and {{ $luggage }} {{ Str::plural('bag', (int) $luggage) }}@endif:
        <strong>{{ $suggestedVehicle?->type ?? 'none available' }}</strong>.
        Only vehicles with enough seats are listed.
        @if ($childSeats) {{ $childSeats }} child {{ Str::plural('seat', $childSeats) }} will be fitted. @endif
    </p>
    <div class="mt-4 grid gap-3 sm:grid-cols-2 xl:grid-cols-3" role="radiogroup" aria-label="Vehicle">
        @foreach ($vehicles as $vehicle)
            @php $on = $vehicleId === $vehicle->id; @endphp
            <button type="button" role="radio" aria-checked="{{ $on ? 'true' : 'false' }}" wire:click="selectVehicle({{ $vehicle->id }})" wire:key="vehicle-{{ $vehicle->id }}"
                    @class(['rounded-xl border-2 bg-white p-4 text-start transition', 'border-primary-700 ring-2 ring-primary-100' => $on, 'border-slate-200 hover:border-primary-300' => ! $on])>
                <span class="flex items-center justify-between gap-2">
                    <span class="font-semibold">{{ $vehicle->type }}</span>
                    <span class="rounded-full bg-slate-100 px-2 text-xs text-slate-600">{{ $vehicle->tier->label() }}</span>
                </span>
                @if ($vehicle->example_model)<span class="block text-xs text-slate-500">e.g. {{ $vehicle->example_model }}</span>@endif
                <span class="mt-2 block text-sm text-slate-600">{{ $vehicle->min_pax }}–{{ $vehicle->max_pax }} seats @if ($vehicle->luggage_capacity) · {{ $vehicle->luggage_capacity }} bags @endif</span>
                <span class="mt-1 block text-sm"><strong>${{ number_format((float) $vehicle->day_rate) }}</strong> <span class="text-slate-500">/ day @if ((float) $vehicle->km_rate > 0) + ${{ number_format((float) $vehicle->km_rate, 2) }}/km @endif</span></span>
                @if ($suggestedVehicle?->is($vehicle))<span class="mt-2 inline-block rounded-full bg-primary-50 px-2 text-xs font-semibold text-primary-800">suggested</span>@endif
            </button>
        @endforeach
    </div>
    @error('vehicleId')<p class="mt-2 text-sm text-red-600" role="alert">{{ $message }}</p>@enderror
</section>

<section aria-labelledby="guide-heading" class="mt-8 rounded-2xl border border-slate-200 bg-white p-5">
    <h2 id="guide-heading" class="font-semibold">Guide</h2>
    <p class="mt-1 text-sm text-slate-600">{{ $guideRule['note'] }}</p>
    <div class="mt-4 grid gap-4 sm:grid-cols-2">
        <fieldset>
            <legend class="form-label">Guide type</legend>
            <div class="space-y-2">
                @foreach ($guideRule['options'] as $option)
                    <label class="flex cursor-pointer items-center gap-3 rounded-lg p-2 hover:bg-slate-50">
                        <input type="radio" wire:model.live="guideType" value="{{ $option->value }}" class="text-primary-700 focus:ring-primary-500">
                        <span>{{ $option->label() }}</span>
                        @if ($option === $guideRule['type'])<span class="rounded-full bg-primary-50 px-2 text-xs text-primary-800">suggested</span>@endif
                    </label>
                @endforeach
            </div>
            @error('guideType')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
        </fieldset>
        @if ($guideType !== 'none')
            <div>
                <label for="guideLanguage" class="form-label">Language</label>
                <select id="guideLanguage" wire:model.live="guideLanguage" class="form-control">
                    @foreach ($languages as $language)
                        <option value="{{ $language }}">{{ $language }}</option>
                    @endforeach
                </select>
                @error('guideLanguage')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
            </div>
        @endif
    </div>
</section>
