{{--
    Bulk action bar. Row checkboxes use form="{{ $form }}", name="ids[]" and data-bulk-item.
    Wrap the bar and the table in an element with x-data="bulkSelect".
--}}
@props(['form', 'action', 'actions' => []])

<form id="{{ $form }}" method="POST" action="{{ $action }}" class="flex flex-wrap items-center gap-2 border-b border-slate-200 bg-slate-50 px-4 py-3">
    @csrf
    <label class="inline-flex items-center gap-2 text-sm text-slate-600">
        <input type="checkbox" class="form-check" @change="toggleAll($event.target.checked)" aria-label="Select all rows on this page">
        <span><span x-text="count">0</span> selected</span>
    </label>
    <label for="{{ $form }}-action" class="sr-only">Bulk action</label>
    <select id="{{ $form }}-action" name="action" class="form-control w-auto py-1.5">
        @foreach ($actions as $value => $label)
            <option value="{{ $value }}">{{ $label }}</option>
        @endforeach
    </select>
    <button type="submit" class="btn btn-secondary btn-sm" :disabled="count === 0">Apply</button>
    @error('ids')<span class="text-xs text-red-600">{{ $message }}</span>@enderror
</form>
