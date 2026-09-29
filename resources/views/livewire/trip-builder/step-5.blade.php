<div class="grid gap-6 md:grid-cols-2">
    <div class="rounded-2xl border border-slate-200 bg-white p-5">
        <h2 class="font-semibold">When</h2>
        <div class="mt-4 grid gap-4 sm:grid-cols-2">
            <div>
                <label for="startDate" class="form-label">Start date <span class="text-red-600" aria-hidden="true">*</span></label>
                <input type="date" id="startDate" wire:model.live="startDate" min="{{ now()->toDateString() }}" max="{{ now()->addYears(2)->toDateString() }}" class="form-control" required
                       @error('startDate') aria-invalid="true" aria-describedby="startDate-error" @enderror>
                @error('startDate')<p id="startDate-error" class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
            </div>
            <div>
                <label for="days" class="form-label">Number of days <span class="text-red-600" aria-hidden="true">*</span></label>
                <input type="number" id="days" wire:model.blur="days" min="1" max="21" class="form-control" required
                       @error('days') aria-invalid="true" aria-describedby="days-error" @enderror>
                @error('days')<p id="days-error" class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                @if ($recommended)
                    <p class="mt-1 text-xs text-slate-500">
                        We recommend {{ $recommended }} {{ Str::plural('day', $recommended) }}.
                        @if ((int) $days !== $recommended)
                            <button type="button" wire:click="useRecommendedDays" class="font-semibold text-primary-700 underline">Use {{ $recommended }}</button>
                        @endif
                    </p>
                @endif
            </div>
        </div>

        <div class="mt-4 grid gap-4 sm:grid-cols-2">
            @foreach (['arrivalPoint' => 'Arriving at', 'departurePoint' => 'Leaving from'] as $field => $label)
                <div>
                    <label for="{{ $field }}" class="form-label">{{ $label }}</label>
                    <select id="{{ $field }}" wire:model.live="{{ $field }}" class="form-control">
                        @foreach ($arrivalPoints as $key => $point)
                            <option value="{{ $key }}">{{ $point['label'] }}</option>
                        @endforeach
                    </select>
                    @error($field)<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                </div>
            @endforeach
        </div>
    </div>

    <div class="rounded-2xl border border-slate-200 bg-white p-5">
        <h2 class="font-semibold">Who is travelling</h2>
        <div class="mt-4 grid gap-4 sm:grid-cols-3">
            <div>
                <label for="adults" class="form-label">Adults</label>
                <input type="number" id="adults" wire:model.blur="adults" min="1" max="40" class="form-control">
                @error('adults')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
            </div>
            <div>
                <label for="infants" class="form-label">Infants <span class="font-normal text-slate-500">(under 2)</span></label>
                <input type="number" id="infants" wire:model.blur="infants" min="0" max="10" class="form-control">
                @error('infants')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
            </div>
            <div>
                <label for="luggage" class="form-label">Bags</label>
                <input type="number" id="luggage" wire:model.blur="luggage" min="0" max="80" placeholder="{{ $adults * 2 }}" class="form-control">
                @error('luggage')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
            </div>
        </div>

        <fieldset class="mt-5">
            <legend class="form-label">Children (2–17)</legend>
            <div class="flex flex-wrap items-end gap-3">
                @foreach ($childrenAges as $i => $age)
                    <div wire:key="child-{{ $i }}" class="flex items-end gap-1">
                        <div>
                            <label for="child-{{ $i }}" class="text-xs text-slate-500">Child {{ $i + 1 }} age</label>
                            <input type="number" id="child-{{ $i }}" wire:model.blur="childrenAges.{{ $i }}" min="2" max="17" class="form-control w-20">
                        </div>
                        <button type="button" wire:click="removeChild({{ $i }})" class="btn btn-secondary btn-sm mb-0.5" aria-label="Remove child {{ $i + 1 }}"><x-icon name="x" class="size-4" /></button>
                    </div>
                @endforeach
                @if (count($childrenAges) < 10)
                    <button type="button" wire:click="addChild" class="btn btn-secondary">+ Add a child</button>
                @endif
            </div>
            @error('childrenAges.*')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
        </fieldset>
        <p class="mt-4 text-xs text-slate-500">Children under 4 and infants get a child seat. More than two bags per person moves you up one vehicle size.</p>
    </div>
</div>
