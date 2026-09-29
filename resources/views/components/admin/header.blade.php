{{-- Page heading with optional action buttons: <x-admin.header title="Places" subtitle="…">buttons</x-admin.header> --}}
@props(['title', 'subtitle' => null, 'back' => null])

<div class="mb-6 flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
    <div class="min-w-0">
        @if ($back)
            <a href="{{ $back }}" class="mb-1 inline-flex items-center gap-1 text-sm font-medium text-primary-700 hover:underline">
                <x-icon name="chevrons-left" class="size-4" /> Back
            </a>
        @endif
        <h2 class="text-2xl font-bold text-slate-900">{{ $title }}</h2>
        @if ($subtitle)
            <p class="mt-1 text-sm text-slate-600">{{ $subtitle }}</p>
        @endif
    </div>
    @if ($slot->isNotEmpty())
        <div class="flex flex-wrap items-center gap-2">{{ $slot }}</div>
    @endif
</div>
