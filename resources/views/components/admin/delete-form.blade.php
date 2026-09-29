{{-- Delete button with a confirmation dialog. --}}
@props(['action', 'label' => 'Delete', 'confirm' => 'Delete this item? This cannot be undone.', 'method' => 'DELETE', 'button' => 'btn-link-danger text-sm font-medium'])

<form method="POST" action="{{ $action }}" onsubmit="return confirm(@js($confirm))" {{ $attributes->class(['inline']) }}>
    @csrf
    @method($method)
    <button type="submit" class="{{ $button }}">{{ $label }}</button>
</form>
