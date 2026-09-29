{{-- Labelled input with old() value and inline error. --}}
@props(['name', 'label', 'type' => 'text', 'value' => null, 'required' => false, 'help' => null])

@php
    $key = str_replace(['[', ']'], ['.', ''], $name);
    $id = $attributes->get('id', 'f-'.str_replace('.', '-', $key));
    $current = $type === 'password' ? null : old($key, $value instanceof \BackedEnum ? $value->value : $value);
@endphp

<div>
    <label for="{{ $id }}" class="form-label">{{ $label }}@if ($required)<span class="text-red-600" aria-hidden="true"> *</span>@endif</label>
    <input type="{{ $type }}" name="{{ $name }}" id="{{ $id }}" value="{{ $current }}" @required($required)
           @error($key) aria-invalid="true" aria-describedby="{{ $id }}-error" @enderror
           {{ $attributes->except('id')->class(['form-control', 'border-red-400' => $errors->has($key)]) }}>
    @if ($help)
        <p class="mt-1 text-xs text-slate-500">{{ $help }}</p>
    @endif
    @error($key)
        <p id="{{ $id }}-error" class="mt-1 text-xs text-red-600">{{ $message }}</p>
    @enderror
</div>
