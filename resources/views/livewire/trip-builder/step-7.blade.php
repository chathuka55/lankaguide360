{{-- How do you want to travel: driver only, driver + guide, or self-drive (Sri Lankan residents only) --}}
<section aria-labelledby="travel-mode-heading" class="mb-8">
    <h2 id="travel-mode-heading" class="font-semibold">How do you want to travel?</h2>
    @php
        $modes = [
            'driver' => ['With a driver', 'A professional driver takes you everywhere. Add site guides at the big sites if you like.', 'truck', ! $driverOnlyAllowed, 'Groups of 10 or more always travel with a guide.'],
            'guide' => ['Driver + guide', 'Your driver is also your guide (chauffeur-guide), or a national guide rides along.', 'user-group', false, null],
            'self' => ['Self-drive', 'Drive yourself with a valid Sri Lankan licence. No driver or chauffeur-guide.', 'key', ! $canSelfDrive || ! $selfDriveFits,
                ! $canSelfDrive ? 'For Sri Lankan residents (choose “Sri Lankan resident” in step 5).' : 'Self-drive vehicles seat up to '.\App\Models\Vehicle::selfDrive()->max('max_pax').' people.'],
        ];
    @endphp
    <div class="mt-3 grid gap-3 sm:grid-cols-3" role="radiogroup" aria-labelledby="travel-mode-heading">
        @foreach ($modes as $mode => [$label, $hint, $icon, $disabled, $why])
            @php $on = $driveMode === $mode; @endphp
            <button type="button" role="radio" aria-checked="{{ $on ? 'true' : 'false' }}" wire:click="chooseDriveMode('{{ $mode }}')" @disabled($disabled)
                    @class(['flex flex-col gap-2 rounded-2xl border-2 bg-white p-4 text-start transition disabled:cursor-not-allowed disabled:opacity-55', 'border-primary-700 ring-2 ring-primary-100' => $on, 'border-slate-200 hover:border-primary-300' => ! $on])>
                <span class="flex items-center justify-between">
                    <span @class(['grid size-10 place-items-center rounded-xl', 'bg-primary-700 text-white' => $on, 'bg-primary-50 text-primary-700' => ! $on])><x-icon :name="$icon" class="size-5" /></span>
                    @if ($on)<x-icon name="check" class="size-5 text-primary-700" />@endif
                </span>
                <span class="text-sm font-semibold text-slate-900">{{ $label }}</span>
                <span class="text-xs text-slate-500">{{ $disabled ? $why : $hint }}</span>
            </button>
        @endforeach
    </div>
</section>

<section aria-labelledby="vehicle-heading">
    <h2 id="vehicle-heading" class="font-semibold">{{ $driveMode === 'self' ? 'Self-drive vehicle' : 'Vehicle with driver' }}</h2>

    <p class="mt-3 text-sm text-slate-600">
        Suggested for {{ $summary['travellers'] }} {{ Str::plural('traveller', $summary['travellers']) }}@if ($luggage !== null) and {{ $luggage }} {{ Str::plural('bag', (int) $luggage) }}@endif:
        <strong>{{ $suggestedVehicle?->type ?? 'none available' }}</strong>.
        Only vehicles with enough seats are listed.
        @if ($childSeats) {{ $childSeats }} child {{ Str::plural('seat', $childSeats) }} will be fitted. @endif
    </p>
    <div class="mt-4 grid gap-3 sm:grid-cols-2 xl:grid-cols-3" role="radiogroup" aria-label="Vehicle">
        @foreach ($vehicles as $vehicle)
            @php $on = $vehicleId === $vehicle->id; @endphp
            <button type="button" role="radio" aria-checked="{{ $on ? 'true' : 'false' }}" wire:click="selectVehicle({{ $vehicle->id }})" wire:key="vehicle-{{ $vehicle->id }}"
                    @class(['overflow-hidden rounded-xl border-2 bg-white p-4 text-start transition', 'border-primary-700 ring-2 ring-primary-100' => $on, 'border-slate-200 hover:border-primary-300' => ! $on])>
                @if ($vehicle->cover)
                    <span class="-mx-4 -mt-4 mb-3 block aspect-video overflow-hidden bg-slate-100">
                        <x-media-img :media="$vehicle->cover" :width="400" sizes="(min-width: 1280px) 20vw, (min-width: 640px) 40vw, 90vw" :alt="$vehicle->type" class="size-full object-cover" />
                    </span>
                @endif
                <span class="flex items-center justify-between gap-2">
                    <span class="font-semibold">{{ $vehicle->type }}</span>
                    <span class="rounded-full bg-slate-100 px-2 text-xs text-slate-600">{{ $vehicle->tier->label() }}</span>
                </span>
                @if ($vehicle->example_model)<span class="block text-xs text-slate-500">e.g. {{ $vehicle->example_model }}</span>@endif
                @if ($vehicle->amenities)
                    <span class="mt-2 flex flex-wrap gap-1">
                        @foreach ($vehicle->amenities as $amenity)
                            <span class="rounded-full bg-slate-100 px-2 py-0.5 text-[11px] text-slate-600">{{ \App\Models\Vehicle::AMENITIES[$amenity] ?? $amenity }}</span>
                        @endforeach
                    </span>
                @endif
                <span class="mt-2 block text-sm text-slate-600">{{ $vehicle->min_pax }}–{{ $vehicle->max_pax }} seats @if ($vehicle->luggage_capacity) · {{ $vehicle->luggage_capacity }} bags @endif</span>
                @if ($selfDrive && $canSelfDrive)
                    <span class="mt-1 block text-sm"><strong>@money($vehicle->self_drive_day_rate)</strong> <span class="text-slate-500">/ day, {{ $vehicle->self_drive_km_per_day }} km a day included</span></span>
                    @if ((float) $vehicle->self_drive_deposit > 0)<span class="block text-xs text-slate-500">Refundable deposit @money($vehicle->self_drive_deposit)</span>@endif
                @else
                    <span class="mt-1 block text-sm"><strong>@money($vehicle->day_rate)</strong> <span class="text-slate-500">/ day @if ((float) $vehicle->km_rate > 0) + @money($vehicle->km_rate)/km @endif</span></span>
                @endif
                @if ($suggestedVehicle?->is($vehicle))<span class="mt-2 inline-block rounded-full bg-primary-50 px-2 text-xs font-semibold text-primary-800">suggested</span>@endif
            </button>
        @endforeach
    </div>
    @error('vehicleId')<p class="mt-2 text-sm text-red-600" role="alert">{{ $message }}</p>@enderror

    @if ($selfDrive && $canSelfDrive)
        <fieldset class="mt-5 rounded-2xl border border-accent-200 bg-accent-50/60 p-5">
            <legend class="px-1 text-sm font-semibold text-slate-900">Driving licence</legend>
            <p class="text-sm text-slate-600">
                The driver needs a valid Sri Lankan driving licence for this vehicle class until {{ $lastDay?->format('j M Y') ?? 'the end of the trip' }}.
                Your agent checks the licence and NIC when handing over the vehicle. Fuel is not included.
            </p>
            <div class="mt-4 grid gap-4 sm:grid-cols-3">
                <div>
                    <label for="driverName" class="form-label">Driver's full name</label>
                    <input id="driverName" type="text" wire:model.blur="driverName" autocomplete="name" class="form-control">
                    @error('driverName')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label for="driverLicenceNo" class="form-label">Licence number</label>
                    <input id="driverLicenceNo" type="text" wire:model.blur="driverLicenceNo" placeholder="B1234567" class="form-control uppercase">
                    @error('driverLicenceNo')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label for="driverLicenceExpiry" class="form-label">Valid until</label>
                    <input id="driverLicenceExpiry" type="date" wire:model.blur="driverLicenceExpiry" min="{{ $lastDay?->toDateString() }}" class="form-control">
                    @error('driverLicenceExpiry')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                </div>
            </div>
            <label class="mt-4 flex items-start gap-3 text-sm">
                <input type="checkbox" wire:model.live="licenceConfirmed" class="form-check mt-0.5">
                <span>I am a Sri Lankan resident and hold a valid Sri Lankan driving licence for this vehicle. I will bring it and my NIC to the pick-up.</span>
            </label>
            @error('licenceConfirmed')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
        </fieldset>
    @endif

    @if ($refreshmentRate > 0)
        <label class="mt-4 flex cursor-pointer items-start gap-3 rounded-xl border border-slate-200 bg-white p-4">
            <input type="checkbox" wire:model.live="refreshments" class="mt-0.5 rounded text-primary-700 focus:ring-primary-500">
            <span class="text-sm">
                <span class="block font-semibold">Add a refreshment pack</span>
                <span class="text-slate-600">Chilled water, soft drinks and snacks in the vehicle every day · @money($refreshmentRate) per person per day</span>
            </span>
        </label>
    @endif
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
