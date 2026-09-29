{{-- Boolean checkbox that always submits a value (hidden 0 + checkbox 1). --}}
@props(['name', 'label', 'checked' => false, 'help' => null])

@php
    $key = str_replace(['[', ']'], ['.', ''], $name);
    $id = 'f-'.str_replace('.', '-', $key);
@endphp

<div>
    <input type="hidden" name="{{ $name }}" value="0">
    <label for="{{ $id }}" class="inline-flex items-start gap-2 text-sm text-slate-700">
        <input type="checkbox" name="{{ $name }}" id="{{ $id }}" value="1" @checked(old($key, $checked)) {{ $attributes->class(['form-check mt-0.5']) }}>
        <span>
            <span class="font-medium">{{ $label }}</span>
            @if ($help)<span class="block text-xs text-slate-500">{{ $help }}</span>@endif
        </span>
    </label>
    @error($key)
        <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
    @enderror
</div>
