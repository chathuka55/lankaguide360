{{-- Phone, country, nationality, Local/Foreign and NIC (register + profile). Needs Alpine. --}}
@props(['user' => null, 'required' => true])

@php
    $countries = \App\Support\TravellerProfile::COUNTRIES;
    $country = old('country', $user?->country);
    $nationality = old('nationality', $user?->nationality);
@endphp

<div x-data="{ country: @js($country) }" class="space-y-4">
    <div>
        <x-input-label for="phone" :value="__('Phone number').($required ? ' *' : '')" />
        <x-text-input id="phone" class="mt-1 block w-full" type="tel" name="phone" :value="old('phone', $user?->phone)" :required="$required" autocomplete="tel" placeholder="+94 77 123 4567" />
        <x-input-error :messages="$errors->get('phone')" class="mt-2" />
    </div>

    <div class="grid gap-4 sm:grid-cols-2">
        <div>
            <x-input-label for="country" :value="__('Country you live in').($required ? ' *' : '')" />
            <select id="country" name="country" x-model="country" @required($required) class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500">
                <option value="">Choose…</option>
                @foreach ($countries as $name)
                    <option value="{{ $name }}" @selected($country === $name)>{{ $name }}</option>
                @endforeach
            </select>
            <x-input-error :messages="$errors->get('country')" class="mt-2" />
        </div>
        <div>
            <x-input-label for="nationality" :value="__('Nationality').($required ? ' *' : '')" />
            <select id="nationality" name="nationality" @required($required) class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500">
                <option value="">Choose…</option>
                @foreach ($countries as $name)
                    <option value="{{ $name }}" @selected($nationality === $name)>{{ $name === 'Sri Lanka' ? 'Sri Lankan' : $name }}</option>
                @endforeach
            </select>
            <x-input-error :messages="$errors->get('nationality')" class="mt-2" />
        </div>
    </div>

    <p x-show="country" x-cloak class="rounded-lg px-3 py-2 text-sm" :class="country === 'Sri Lanka' ? 'bg-primary-50 text-primary-900' : 'bg-slate-100 text-slate-700'">
        <span x-text="country === 'Sri Lanka' ? 'You will be registered as a local traveller (Sri Lankan resident prices).' : 'You will be registered as a foreign traveller.'"></span>
    </p>

    <div x-show="country === 'Sri Lanka'" x-cloak>
        <x-input-label for="nic" :value="__('NIC number *')" />
        <x-text-input id="nic" class="mt-1 block w-full uppercase" type="text" name="nic" :value="old('nic', $user?->nic)" maxlength="14" placeholder="199012345678 or 901234567V" x-bind:required="country === 'Sri Lanka'" />
        <p class="mt-1 text-xs text-gray-500">Needed for Sri Lankan resident prices. Old (9 digits + V/X) or new (12 digits) format.</p>
        <x-input-error :messages="$errors->get('nic')" class="mt-2" />
    </div>
</div>
