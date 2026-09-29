@props(['phase', 'title' => 'This page is being built'])

<div class="mx-auto max-w-3xl px-4 py-16 text-center sm:px-6">
    <div class="mx-auto grid size-14 place-items-center rounded-full bg-accent-100 text-accent-700">
        <x-icon name="sparkles" class="size-7" />
    </div>
    <h2 class="mt-4 text-xl font-bold">{{ $title }}</h2>
    <p class="mt-2 text-slate-600">{{ $slot }}</p>
    <p class="mt-1 text-xs text-slate-400">Arrives in build phase {{ $phase }}.</p>
    <div class="mt-6 flex flex-wrap justify-center gap-3">
        <a href="{{ route('plan') }}" class="rounded-lg bg-primary-700 px-5 py-2.5 text-sm font-semibold text-white hover:bg-primary-800">Start planning</a>
        <a href="{{ route('home') }}" class="rounded-lg border border-slate-300 px-5 py-2.5 text-sm font-semibold text-slate-700 hover:bg-slate-50">Back to Home</a>
    </div>
</div>
