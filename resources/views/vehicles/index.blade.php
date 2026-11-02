<x-app-layout title="Vehicle Hire" description="Hire a car, van or coach with a driver anywhere in Sri Lanka, or self-drive with a Sri Lankan licence. See the vehicles, what is on board and the daily rates, and send a request.">
    <x-page-header eyebrow="Vehicle hire" title="Hire a vehicle, with or without a driver" subtitle="Cars, vans and coaches with an experienced driver. Sri Lankan residents with a valid Sri Lankan driving licence can also hire cars and vans as self-drive." />

    <div class="mx-auto max-w-7xl px-4 py-10 sm:px-6 lg:px-8">
        @if (session('status'))
            <div class="mb-6 rounded-lg border border-primary-200 bg-primary-50 px-4 py-3 text-sm text-primary-800" role="status">{{ session('status') }}</div>
        @endif
        @if ($errors->any())
            <div class="mb-6 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700" role="alert">Please check the request form: {{ $errors->first() }}</div>
        @endif

        <div class="grid gap-6 md:grid-cols-2 xl:grid-cols-3">
            @forelse ($vehicles as $vehicle)
                <article id="vehicle-{{ $vehicle->id }}" class="flex scroll-mt-24 flex-col overflow-hidden rounded-2xl border border-slate-200 bg-white" x-data="{ open: {{ old('vehicle_id') == $vehicle->id ? 'true' : 'false' }} }">
                    <div class="relative aspect-video shrink-0 overflow-hidden bg-gradient-to-br from-primary-50 to-primary-100">
                        @if ($vehicle->cover)
                            <x-media-img :media="$vehicle->cover" :width="800" sizes="(min-width: 1280px) 30vw, (min-width: 768px) 45vw, 100vw" :alt="$vehicle->type" class="size-full object-cover" />
                        @else
                            <div class="grid size-full place-items-center text-primary-700"><x-icon name="truck" class="size-16 opacity-60" /></div>
                        @endif
                        <span class="absolute start-3 top-3 rounded-full bg-white/95 px-2.5 py-1 text-xs font-bold text-primary-800 shadow">{{ $vehicle->tier->label() }}</span>
                        @if ($vehicle->cover)
                            <span class="absolute inset-x-0 bottom-0 bg-gradient-to-t from-slate-950/70 to-transparent px-3 pb-1.5 pt-4 text-end text-[10px] text-white/90"><x-photo-credit :media="$vehicle->cover" class="text-white/90" /></span>
                        @endif
                    </div>
                    <div class="flex flex-1 flex-col gap-3 p-5">
                        <div>
                            <h2 class="text-lg font-semibold">{{ $vehicle->type }}</h2>
                            @if ($vehicle->example_model)<p class="text-sm text-slate-500">e.g. {{ $vehicle->example_model }}</p>@endif
                        </div>
                        @if ($vehicle->description)<p class="text-sm text-slate-600">{{ $vehicle->description }}</p>@endif
                        <dl class="grid grid-cols-2 gap-2 text-sm">
                            <div><dt class="text-slate-500">Travellers</dt><dd class="font-medium">{{ $vehicle->min_pax }}–{{ $vehicle->max_pax }}</dd></div>
                            <div><dt class="text-slate-500">Luggage</dt><dd class="font-medium">{{ $vehicle->luggage_capacity ? $vehicle->luggage_capacity.' bags' : '—' }}</dd></div>
                            <div><dt class="text-slate-500">Per day, with driver</dt><dd class="font-semibold">@money($vehicle->day_rate)</dd></div>
                            <div><dt class="text-slate-500">Per km</dt><dd class="font-medium">{{ (float) $vehicle->km_rate > 0 ? \App\Support\Money::show($vehicle->km_rate) : 'included' }}</dd></div>
                        </dl>
                        @if ($vehicle->offersSelfDrive())
                            <p class="flex items-start gap-2 rounded-xl bg-accent-50 px-3 py-2 text-sm text-slate-700 ring-1 ring-accent-200">
                                <x-icon name="key" class="mt-0.5 size-4 text-accent-700" />
                                <span><strong>Self-drive @money($vehicle->self_drive_day_rate) / day</strong>, {{ $vehicle->self_drive_km_per_day }} km a day included @if ((float) $vehicle->self_drive_deposit > 0) · refundable deposit @money($vehicle->self_drive_deposit) @endif <span class="text-slate-500">Sri Lankan residents with a Sri Lankan licence.</span></span>
                            </p>
                        @endif
                        @if ($vehicle->amenities)
                            <ul class="flex flex-wrap gap-1.5" aria-label="On board">
                                @foreach ($vehicle->amenities as $amenity)
                                    <li class="inline-flex items-center gap-1 rounded-full bg-slate-100 px-2.5 py-1 text-xs text-slate-700"><x-icon name="check" class="size-3 text-primary-700" /> {{ App\Models\Vehicle::AMENITIES[$amenity] ?? $amenity }}</li>
                                @endforeach
                            </ul>
                        @endif
                        <p class="text-xs text-slate-500">
                            Provided by <strong>{{ $vehicle->provider_name ?: 'LankaGuide360' }}</strong>
                            @if ($vehicle->provider_phone) · <a href="tel:{{ preg_replace('/[^\d+]/', '', $vehicle->provider_phone) }}" class="text-primary-700 hover:underline">{{ $vehicle->provider_phone }}</a>@endif
                            @if ($vehicle->provider_website) · <a href="{{ $vehicle->provider_website }}" target="_blank" rel="noopener nofollow" class="text-primary-700 hover:underline">Website</a>@endif
                        </p>
                        <button type="button" @click="open = ! open" :aria-expanded="open.toString()" class="btn btn-primary mt-auto">Request this vehicle</button>

                        <form x-show="open" x-cloak method="POST" action="{{ route('vehicles.request', $vehicle) }}" class="space-y-3 border-t border-slate-200 pt-4"
                              x-data="{ mode: @js(old('vehicle_id') == $vehicle->id ? old('mode', 'driver') : 'driver') }">
                            @csrf
                            <input type="hidden" name="vehicle_id" value="{{ $vehicle->id }}">
                            @if ($vehicle->offersSelfDrive())
                                <fieldset>
                                    <legend class="form-label">Who drives?</legend>
                                    <div class="grid grid-cols-2 gap-2">
                                        <label class="flex cursor-pointer items-center gap-2 rounded-lg border border-slate-200 px-3 py-2 text-sm has-[:checked]:border-primary-700 has-[:checked]:bg-primary-50">
                                            <input type="radio" name="mode" value="driver" x-model="mode" class="text-primary-700 focus:ring-primary-500"> With a driver
                                        </label>
                                        <label class="flex cursor-pointer items-center gap-2 rounded-lg border border-slate-200 px-3 py-2 text-sm has-[:checked]:border-primary-700 has-[:checked]:bg-primary-50">
                                            <input type="radio" name="mode" value="self" x-model="mode" class="text-primary-700 focus:ring-primary-500"> Self-drive
                                        </label>
                                    </div>
                                </fieldset>
                                <div x-show="mode === 'self'" x-cloak class="space-y-3 rounded-xl bg-accent-50/70 p-3 ring-1 ring-accent-200">
                                    <p class="text-xs text-slate-600">For Sri Lankan residents only. Bring your licence and NIC to the pick-up; fuel is not included.</p>
                                    <div><label class="form-label" for="hire-{{ $vehicle->id }}-driver">Driver's full name</label><input id="hire-{{ $vehicle->id }}-driver" name="driver_name" value="{{ old('driver_name', auth()->user()?->name) }}" maxlength="120" class="form-control" :required="mode === 'self'" :disabled="mode !== 'self'"></div>
                                    <div class="grid gap-3 sm:grid-cols-2">
                                        <div><label class="form-label" for="hire-{{ $vehicle->id }}-licence">Licence number</label><input id="hire-{{ $vehicle->id }}-licence" name="licence_no" value="{{ old('licence_no') }}" maxlength="20" placeholder="B1234567" class="form-control uppercase" :required="mode === 'self'" :disabled="mode !== 'self'"></div>
                                        <div><label class="form-label" for="hire-{{ $vehicle->id }}-expiry">Valid until</label><input type="date" id="hire-{{ $vehicle->id }}-expiry" name="licence_expiry" value="{{ old('licence_expiry') }}" min="{{ now()->toDateString() }}" class="form-control" :required="mode === 'self'" :disabled="mode !== 'self'"></div>
                                    </div>
                                    <label class="flex items-start gap-2 text-sm"><input type="checkbox" name="resident" value="1" class="form-check mt-0.5" :required="mode === 'self'" :disabled="mode !== 'self'"> I am a Sri Lankan resident with a valid Sri Lankan driving licence for this vehicle.</label>
                                </div>
                            @endif
                            <div class="hidden" aria-hidden="true"><label>Website <input type="text" name="website" tabindex="-1" autocomplete="off"></label></div>
                            <div class="grid gap-3 sm:grid-cols-2">
                                <div><label class="form-label" for="hire-{{ $vehicle->id }}-start">From</label><input type="date" id="hire-{{ $vehicle->id }}-start" name="start_date" min="{{ now()->toDateString() }}" value="{{ old('start_date') }}" required class="form-control"></div>
                                <div><label class="form-label" for="hire-{{ $vehicle->id }}-end">To</label><input type="date" id="hire-{{ $vehicle->id }}-end" name="end_date" min="{{ now()->toDateString() }}" value="{{ old('end_date') }}" required class="form-control"></div>
                            </div>
                            <div class="grid gap-3 sm:grid-cols-2">
                                <div>
                                    <label class="form-label" for="hire-{{ $vehicle->id }}-pickup">Pick-up</label>
                                    <select id="hire-{{ $vehicle->id }}-pickup" name="pickup" required class="form-control">
                                        @foreach ($points as $group => $options)
                                            <optgroup label="{{ $group }}">
                                                @foreach ($options as $key => $label)<option value="{{ $key }}" @selected(old('pickup') === $key)>{{ $label }}</option>@endforeach
                                            </optgroup>
                                        @endforeach
                                    </select>
                                </div>
                                <div><label class="form-label" for="hire-{{ $vehicle->id }}-pax">Travellers</label><input type="number" id="hire-{{ $vehicle->id }}-pax" name="travellers" min="1" max="{{ $vehicle->max_pax }}" value="{{ old('travellers', $vehicle->min_pax) }}" required class="form-control"></div>
                            </div>
                            <div><label class="form-label" for="hire-{{ $vehicle->id }}-name">Your name</label><input id="hire-{{ $vehicle->id }}-name" name="name" value="{{ old('name', auth()->user()?->name) }}" required maxlength="120" class="form-control" autocomplete="name"></div>
                            <div class="grid gap-3 sm:grid-cols-2">
                                <div><label class="form-label" for="hire-{{ $vehicle->id }}-email">Email</label><input type="email" id="hire-{{ $vehicle->id }}-email" name="email" value="{{ old('email', auth()->user()?->email) }}" required class="form-control" autocomplete="email"></div>
                                <div><label class="form-label" for="hire-{{ $vehicle->id }}-phone">Phone</label><input type="tel" id="hire-{{ $vehicle->id }}-phone" name="phone" value="{{ old('phone', auth()->user()?->phone) }}" required placeholder="+94 77 123 4567" class="form-control" autocomplete="tel"></div>
                            </div>
                            <div><label class="form-label" for="hire-{{ $vehicle->id }}-msg">Anything else? (optional)</label><textarea id="hire-{{ $vehicle->id }}-msg" name="message" rows="2" maxlength="2000" class="form-control">{{ old('message') }}</textarea></div>
                            <button type="submit" class="btn btn-primary w-full">Send request</button>
                        </form>
                    </div>
                </article>
            @empty
                <p class="text-slate-600">No vehicles are available for hire right now. Please <a href="{{ route('contact') }}" class="text-primary-700 underline">contact us</a>.</p>
            @endforelse
        </div>

        <p class="mt-8 text-sm text-slate-500">Planning a whole trip? The <a href="{{ route('plan') }}" class="font-semibold text-primary-700 underline">Trip Builder</a> picks the right vehicle for your group automatically.</p>
    </div>
</x-app-layout>
