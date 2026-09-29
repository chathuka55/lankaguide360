{{-- Compact select for filter bars; value comes from the query string. --}}
@props(['name', 'label', 'options' => [], 'placeholder' => 'All'])

<div class="lg:w-44">
    <label for="filter-{{ $name }}" class="form-label">{{ $label }}</label>
    <select name="{{ $name }}" id="filter-{{ $name }}" class="form-control">
        <option value="">{{ $placeholder }}</option>
        @foreach ($options as $value => $text)
            <option value="{{ $value }}" @selected((string) request($name) === (string) $value)>{{ $text }}</option>
        @endforeach
    </select>
</div>
