@php
    $priceData = $price?->toArray();
    $lines = collect($priceData['lines'] ?? [])->groupBy('category');
    $user = auth()->user();
    $countries = ['Sri Lanka', 'India', 'United Kingdom', 'Germany', 'France', 'Netherlands', 'Italy', 'Spain', 'Switzerland', 'Austria', 'Belgium', 'Sweden', 'Norway', 'Denmark', 'Poland', 'Russia', 'Ukraine', 'United States', 'Canada', 'Australia', 'New Zealand', 'China', 'Japan', 'South Korea', 'Singapore', 'Malaysia', 'Maldives', 'United Arab Emirates', 'Saudi Arabia', 'Israel', 'Bangladesh', 'Pakistan', 'Nepal'];
@endphp

@include('livewire.trip-builder.plan-notices')

<div class="flex flex-wrap gap-2 text-sm">
    <span class="text-slate-500">Edit:</span>
    @foreach ([1 => 'Style', 2 => 'Interests', 3 => 'Districts', 4 => 'Places', 5 => 'Dates', 6 => 'Hotels & meals', 7 => 'Transport'] as $number => $label)
        <button type="button" wire:click="goTo({{ $number }})" class="rounded-full bg-slate-100 px-3 py-1 text-slate-700 hover:bg-slate-200">{{ $label }}</button>
    @endforeach
</div>

{{-- Map with a day filter --}}
<section class="mt-5" aria-label="Route map" x-data="{ day: null }">
    <div class="mb-2 flex flex-wrap gap-1 text-xs">
        <button type="button" @click="day = null; $dispatch('trip-map-filter', { day: null })" :class="day === null ? 'bg-slate-900 text-white' : 'bg-slate-100 text-slate-700'" class="rounded-full px-3 py-1 font-medium">All days</button>
        @foreach ($itinerary->days as $day)
            <button type="button" @click="day = {{ $day['number'] }}; $dispatch('trip-map-filter', { day: {{ $day['number'] }} })" :class="day === {{ $day['number'] }} ? 'bg-slate-900 text-white' : 'bg-slate-100 text-slate-700'" class="rounded-full px-3 py-1 font-medium">Day {{ $day['number'] }}</button>
        @endforeach
    </div>
    <div wire:ignore class="h-80 overflow-hidden rounded-2xl border border-slate-200 bg-slate-100 sm:h-96"
         x-data="tripMap({ days: @js($mapDays), start: @js($start ? ['lat' => $start['lat'], 'lng' => $start['lng'], 'name' => $draft->arrivalPoint] : null) })"></div>
</section>

{{-- Day-by-day timeline; drag stops to reorder or move them to another day --}}
<section class="mt-8" aria-labelledby="timeline-heading">
    <div class="flex flex-wrap items-baseline justify-between gap-2">
        <h2 id="timeline-heading" class="text-xl font-bold">Day by day</h2>
        <p class="text-xs text-slate-500">Drag a stop to change the order or move it to another day. Times update automatically.</p>
    </div>
    <ol class="mt-4 space-y-4" x-data="itinerarySort()" wire:key="timeline-{{ md5(json_encode($itinerary->placeIds())) }}">
        @foreach ($itinerary->days as $day)
            @php
                $choice = $draft->hotels[$day['number']] ?? null;
                $hotel = $choice ? ($hotels[$choice['hotel_id']] ?? null) : null;
            @endphp
            <li class="rounded-2xl border border-slate-200 bg-white p-5" wire:key="day-{{ $day['number'] }}">
                <div class="flex flex-wrap items-baseline justify-between gap-2">
                    <h3 class="font-semibold"><span class="text-primary-700">Day {{ $day['number'] }}</span> · {{ $day['title'] }}</h3>
                    <p class="text-xs text-slate-500">
                        {{ \Illuminate\Support\Carbon::parse($day['date'])->format('D j M') }}
                        @if (! empty($day['start']['label'])) · from {{ $day['start']['label'] }} @endif
                        @if ($day['drive_km'] > 0) · {{ number_format($day['drive_km']) }} km, {{ intdiv($day['drive_minutes'], 60) }}h {{ $day['drive_minutes'] % 60 }}m driving @endif
                    </p>
                </div>

                <ul class="mt-3 min-h-10 space-y-2 rounded-lg" data-day="{{ $day['number'] }}" x-ref="day{{ $day['number'] }}">
                    @foreach ($day['stops'] as $stop)
                        <li data-place-id="{{ $stop['place_id'] }}" wire:key="stop-{{ $day['number'] }}-{{ $stop['place_id'] }}"
                            class="flex cursor-grab items-start gap-3 rounded-lg border border-slate-200 bg-slate-50 p-3 active:cursor-grabbing">
                            <span class="mt-0.5 text-slate-400" aria-hidden="true">⋮⋮</span>
                            <div class="min-w-0 flex-1">
                                <p class="text-sm">
                                    <span class="font-mono text-xs text-slate-500">{{ $stop['arrive'] }}–{{ $stop['depart'] }}</span>
                                    <span class="font-semibold">{{ $stop['name'] }}</span>
                                    <span class="text-xs text-slate-500">{{ $stop['district'] }}</span>
                                </p>
                                @if ($stop['km_from_prev'] > 0)
                                    <p class="text-xs text-slate-500">{{ number_format($stop['km_from_prev']) }} km · {{ $stop['minutes_from_prev'] }} min drive before</p>
                                @endif
                            </div>
                        </li>
                    @endforeach
                </ul>
                @if (! $day['stops'])
                    <p class="mt-1 text-sm text-slate-500">{{ $day['type'] === 'departure' ? 'Transfer to '.$draft->departurePoint.'.' : 'A relaxed day: enjoy the beach, a spa or a walk around town.' }}</p>
                @endif

                <div class="mt-3 flex flex-wrap gap-x-4 gap-y-1 text-xs text-slate-600">
                    @if (! empty($day['lunch_at']))<span>🍽 Lunch around {{ $day['lunch_at'] }}</span>@endif
                    @if (! empty($day['end_transfer']))<span>🚗 {{ $day['end_transfer']['to'] }}: {{ number_format($day['end_transfer']['km']) }} km, arrive {{ $day['end_transfer']['arrive'] }}</span>@endif
                    @if (! empty($day['overnight']))
                        <span>🛏 {{ $hotel?->name ?? 'Hotel to be confirmed' }}, {{ $day['overnight']['town'] }} · {{ \App\Enums\MealPlan::tryFrom($mealPlan)?->label() }}</span>
                    @endif
                </div>
            </li>
        @endforeach
    </ol>
</section>

{{-- Price --}}
<section class="mt-8 rounded-2xl border border-slate-200 bg-white p-5" aria-labelledby="price-heading">
    <h2 id="price-heading" class="text-xl font-bold">Price estimate</h2>
    <p class="text-sm text-slate-500">Estimate — final price confirmed by your agent.</p>
    @if ($priceData)
        <div class="mt-4 overflow-x-auto">
            <table class="w-full text-sm">
                @foreach ($lines as $category => $categoryLines)
                    <tbody class="border-b border-slate-100">
                        <tr><th colspan="2" class="pt-3 text-start font-semibold text-slate-700">{{ $categoryLabels[$category] ?? $category }}</th><td class="pt-3 text-end font-semibold">${{ number_format((float) $priceData['categories'][$category], 2) }}</td></tr>
                        @foreach ($categoryLines as $line)
                            <tr class="text-slate-600">
                                <td class="py-1 pe-3">{{ $line['description'] }}</td>
                                <td class="whitespace-nowrap py-1 pe-3 text-end text-xs">{{ rtrim(rtrim(number_format((float) $line['qty'], 1), '0'), '.') }} × ${{ number_format((float) $line['unit_price'], 2) }}</td>
                                <td class="whitespace-nowrap py-1 text-end">${{ number_format((float) $line['amount'], 2) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                @endforeach
                <tfoot>
                    <tr><td colspan="2" class="pt-3 text-end">Subtotal</td><td class="pt-3 text-end">${{ number_format((float) $priceData['subtotal'], 2) }}</td></tr>
                    <tr><td colspan="2" class="text-end">Service fee</td><td class="text-end">${{ number_format((float) $priceData['service_fee'], 2) }}</td></tr>
                    @if ((float) $priceData['tax'] > 0)<tr><td colspan="2" class="text-end">Tax</td><td class="text-end">${{ number_format((float) $priceData['tax'], 2) }}</td></tr>@endif
                    <tr class="text-lg font-extrabold"><td colspan="2" class="pt-2 text-end">Total</td><td class="pt-2 text-end">${{ number_format((float) $priceData['total'], 2) }}</td></tr>
                    <tr class="text-slate-500"><td colspan="2" class="text-end">Per person ({{ $priceData['paying_travellers'] }} paying)</td><td class="text-end">${{ number_format((float) $priceData['per_person'], 2) }}</td></tr>
                    @if ((float) $priceData['total_lkr'] > 0)<tr class="text-xs text-slate-500"><td colspan="2" class="text-end">About</td><td class="text-end">LKR {{ number_format((float) $priceData['total_lkr']) }}</td></tr>@endif
                </tfoot>
            </table>
        </div>
        @foreach ($priceData['notes'] as $note)
            <p class="mt-2 text-xs text-slate-500">{{ $note }}</p>
        @endforeach
    @endif
</section>

{{-- Submit (plain form: TripController@store) --}}
<section class="mt-8 rounded-2xl border-2 border-primary-200 bg-primary-50/40 p-5" aria-labelledby="submit-heading" x-data="{ account: {{ old('create_account') ? 'true' : 'false' }} }">
    <h2 id="submit-heading" class="text-xl font-bold">Send your plan to a travel agent</h2>
    <p class="mt-1 text-sm text-slate-600">An agent checks availability and confirms the final price, usually within one working day. Nothing is booked or charged yet.</p>

    @if ($errors->hasAny(['full_name', 'email', 'phone', 'country', 'age', 'consent', 'password', 'whatsapp']))
        <div class="mt-4 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700" role="alert">Please check the highlighted fields.</div>
    @endif

    <form method="POST" action="{{ route('trips.store') }}" class="mt-5 space-y-4">
        @csrf
        @if ($user)
            <p class="text-sm">Submitting as <strong>{{ $user->name }}</strong> ({{ $user->email }}).</p>
        @else
            <p class="text-sm text-slate-600">
                <a href="{{ route('login') }}" class="font-semibold text-primary-700 underline">Log in</a> or
                <a href="{{ route('register') }}" class="font-semibold text-primary-700 underline">register</a> to keep your trips in one place, or continue as a guest:
            </p>
            <div class="grid gap-4 sm:grid-cols-2">
                @foreach ([['full_name', 'Full name', 'text', 'name'], ['email', 'Email', 'email', 'email'], ['phone', 'Phone (with country code)', 'tel', 'tel'], ['whatsapp', 'WhatsApp (optional)', 'tel', 'tel']] as [$name, $label, $type, $autocomplete])
                    <div>
                        <label for="f-{{ $name }}" class="form-label">{{ $label }}@if ($name !== 'whatsapp')<span class="text-red-600" aria-hidden="true"> *</span>@endif</label>
                        <input type="{{ $type }}" id="f-{{ $name }}" name="{{ $name }}" value="{{ old($name) }}" autocomplete="{{ $autocomplete }}" @required($name !== 'whatsapp')
                               @if (in_array($name, ['phone', 'whatsapp'])) placeholder="+94 77 123 4567" @endif
                               class="form-control @error($name) border-red-400 @enderror" @error($name) aria-invalid="true" @enderror>
                        @error($name)<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                    </div>
                @endforeach
                <div>
                    <label for="f-country" class="form-label">Country<span class="text-red-600" aria-hidden="true"> *</span></label>
                    <input id="f-country" name="country" list="countries" value="{{ old('country') }}" autocomplete="country-name" required class="form-control @error('country') border-red-400 @enderror">
                    <datalist id="countries">@foreach ($countries as $country)<option value="{{ $country }}">@endforeach</datalist>
                    @error('country')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label for="f-age" class="form-label">Age<span class="text-red-600" aria-hidden="true"> *</span></label>
                    <input type="number" id="f-age" name="age" value="{{ old('age') }}" min="16" max="110" required class="form-control @error('age') border-red-400 @enderror">
                    @error('age')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                </div>
            </div>

            @if ($summary['travellers'] > 1)
                <fieldset>
                    <legend class="form-label">Travelling with you (optional)</legend>
                    <div class="grid gap-2 sm:grid-cols-2">
                        @for ($i = 0; $i < min($summary['travellers'] - 1, 20); $i++)
                            <div class="flex gap-2">
                                <label class="sr-only" for="companion-{{ $i }}-name">Companion {{ $i + 1 }} name</label>
                                <input id="companion-{{ $i }}-name" name="companions[{{ $i }}][name]" value="{{ old("companions.$i.name") }}" placeholder="Name" class="form-control">
                                <label class="sr-only" for="companion-{{ $i }}-age">Companion {{ $i + 1 }} age</label>
                                <input id="companion-{{ $i }}-age" type="number" name="companions[{{ $i }}][age]" value="{{ old("companions.$i.age") }}" min="0" max="110" placeholder="Age" class="form-control w-20">
                            </div>
                        @endfor
                    </div>
                </fieldset>
            @endif

            <label class="flex items-center gap-2 text-sm">
                <input type="checkbox" name="create_account" value="1" x-model="account" class="rounded text-primary-700 focus:ring-primary-500">
                Create an account with this email so I can see my trips later
            </label>
            <div x-show="account" x-cloak class="grid gap-4 sm:grid-cols-2">
                <div>
                    <label for="f-password" class="form-label">Password</label>
                    <input type="password" id="f-password" name="password" autocomplete="new-password" class="form-control @error('password') border-red-400 @enderror" x-bind:required="account">
                    @error('password')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label for="f-password-confirmation" class="form-label">Confirm password</label>
                    <input type="password" id="f-password-confirmation" name="password_confirmation" autocomplete="new-password" class="form-control" x-bind:required="account">
                </div>
            </div>
        @endif

        <div>
            <label for="f-special" class="form-label">Special requests</label>
            <textarea id="f-special" name="special_requests" rows="3" maxlength="2000" class="form-control" placeholder="Celebrations, accessibility needs, anything else we should know">{{ old('special_requests', $draft->specialRequests) }}</textarea>
            @error('special_requests')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
        </div>

        <label class="flex items-start gap-2 text-sm">
            <input type="checkbox" name="consent" value="1" required @checked(old('consent')) class="mt-0.5 rounded text-primary-700 focus:ring-primary-500">
            <span>I agree that LankaGuide360 stores my details to plan this trip, as described in the <a href="{{ route('privacy') }}" target="_blank" class="text-primary-700 underline">privacy policy</a>.</span>
        </label>
        @error('consent')<p class="text-xs text-red-600">{{ $message }}</p>@enderror

        <button type="submit" class="btn btn-primary w-full px-6 py-3 text-base sm:w-auto">Submit my trip request</button>
    </form>
</section>
