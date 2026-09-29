{{-- lat/lng inputs + Leaflet map: click or drag the marker to set the position. --}}
@props(['lat' => null, 'lng' => null, 'readonly' => false, 'required' => false])

@php
    $latValue = old('lat', $lat);
    $lngValue = old('lng', $lng);
@endphp

<div x-data="mapPicker({ lat: @js((string) $latValue), lng: @js((string) $lngValue), readonly: @js($readonly) })" class="space-y-3">
    <div class="grid gap-4 sm:grid-cols-2">
        <div>
            <label for="f-lat" class="form-label">Latitude @if ($required)<span class="text-red-600"> *</span>@endif</label>
            <input type="text" inputmode="decimal" name="lat" id="f-lat" x-model="lat" class="form-control @error('lat') border-red-400 @enderror" placeholder="e.g. 7.956944">
            @error('lat')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
        </div>
        <div>
            <label for="f-lng" class="form-label">Longitude @if ($required)<span class="text-red-600"> *</span>@endif</label>
            <input type="text" inputmode="decimal" name="lng" id="f-lng" x-model="lng" class="form-control @error('lng') border-red-400 @enderror" placeholder="e.g. 80.759722">
            @error('lng')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
        </div>
    </div>
    <div x-ref="map" class="h-72 w-full overflow-hidden rounded-lg ring-1 ring-slate-200" role="application" aria-label="Map: click to set the position"></div>
    @unless ($readonly)
        <p class="text-xs text-slate-500">Click the map or drag the marker to set the position. Map data © OpenStreetMap contributors.</p>
    @endunless
</div>
