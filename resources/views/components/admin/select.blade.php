{{-- <x-admin.select name="tier" label="Tier" :options="['budget' => 'Budget']" :value="$hotel->tier" /> --}}
@props(['name', 'label', 'options' => [], 'value' => null, 'required' => false, 'placeholder' => null, 'help' => null])

@php
    $key = str_replace(['[', ']'], ['.', ''], $name);
    $id = 'f-'.str_replace('.', '-', $key);
    $current = (string) old($key, $value instanceof \BackedEnum ? $value->value : $value);
@endphp

<div>
    <label for="{{ $id }}" class="form-label">{{ $label }}@if ($required)<span class="text-red-600" aria-hidden="true"> *</span>@endif</label>
    <select name="{{ $name }}" id="{{ $id }}" @required($required)
            @error($key) aria-invalid="true" aria-describedby="{{ $id }}-error" @enderror
            {{ $attributes->class(['form-control', 'border-red-400' => $errors->has($key)]) }}>
        @if ($placeholder !== null)
            <option value="">{{ $placeholder }}</option>
        @endif
        @foreach ($options as $optionValue => $optionLabel)
            <option value="{{ $optionValue }}" @selected($current === (string) $optionValue)>{{ $optionLabel }}</option>
        @endforeach
    </select>
    @if ($help)
        <p class="mt-1 text-xs text-slate-500">{{ $help }}</p>
    @endif
    @error($key)
        <p id="{{ $id }}-error" class="mt-1 text-xs text-red-600">{{ $message }}</p>
    @enderror
</div>
