@props(['name', 'label', 'value' => null, 'required' => false, 'help' => null, 'rows' => 4])

@php
    $key = str_replace(['[', ']'], ['.', ''], $name);
    $id = 'f-'.str_replace('.', '-', $key);
@endphp

<div>
    <label for="{{ $id }}" class="form-label">{{ $label }}@if ($required)<span class="text-red-600" aria-hidden="true"> *</span>@endif</label>
    <textarea name="{{ $name }}" id="{{ $id }}" rows="{{ $rows }}" @required($required)
              @error($key) aria-invalid="true" aria-describedby="{{ $id }}-error" @enderror
              {{ $attributes->class(['form-control', 'border-red-400' => $errors->has($key)]) }}>{{ old($key, $value) }}</textarea>
    @if ($help)
        <p class="mt-1 text-xs text-slate-500">{{ $help }}</p>
    @endif
    @error($key)
        <p id="{{ $id }}-error" class="mt-1 text-xs text-red-600">{{ $message }}</p>
    @enderror
</div>
