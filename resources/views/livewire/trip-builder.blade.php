<div class="mx-auto max-w-7xl px-4 pb-32 pt-6 sm:px-6 lg:px-8 lg:pb-12">
    {{-- Stepper: completed steps are clickable --}}
    <nav aria-label="Trip Builder steps" class="-mx-4 overflow-x-auto px-4 [scrollbar-width:none] sm:mx-0 sm:px-0">
        <ol class="flex min-w-max items-center gap-1 sm:gap-2">
            @foreach ($steps as $number => $label)
                @php
                    $isCurrent = $number === $step;
                    $isDone = $number <= $completedStep && ! $isCurrent;
                    $canGo = $number <= $completedStep + 1;
                @endphp
                <li class="flex items-center gap-1 sm:gap-2">
                    <button type="button" wire:click="goTo({{ $number }})" @disabled(! $canGo)
                            @if ($isCurrent) aria-current="step" @endif
                            @class([
                                'flex items-center gap-2 rounded-full px-2.5 py-1.5 text-xs font-semibold transition sm:text-sm',
                                'bg-primary-700 text-white shadow' => $isCurrent,
                                'bg-primary-50 text-primary-800 hover:bg-primary-100' => $isDone,
                                'text-slate-500' => ! $isCurrent && ! $isDone,
                                'cursor-not-allowed opacity-60' => ! $canGo,
                            ])>
                        <span @class(['grid size-6 place-items-center rounded-full text-xs', 'bg-white/20' => $isCurrent, 'bg-primary-700 text-white' => $isDone, 'bg-slate-200' => ! $isCurrent && ! $isDone])>
                            @if ($isDone)<x-icon name="check" class="size-3.5" />@else{{ $number }}@endif
                        </span>
                        <span @class(['hidden 2xl:inline' => ! $isCurrent])>{{ $label }}</span>
                    </button>
                    @if (! $loop->last)<span class="h-px w-3 bg-slate-300 sm:w-5" aria-hidden="true"></span>@endif
                </li>
            @endforeach
        </ol>
    </nav>

    <div class="mt-6 grid gap-8 lg:grid-cols-[minmax(0,1fr)_20rem]">
        <section aria-labelledby="step-heading" class="min-w-0">
            <p class="text-sm font-semibold uppercase tracking-wide text-primary-700">Step {{ $step }} of 8</p>
            <h1 id="step-heading" class="mt-1 text-2xl font-extrabold tracking-tight sm:text-3xl">{{ $steps[$step] }}</h1>

            @if (session('status'))
                <div class="mt-4 rounded-lg border border-primary-200 bg-primary-50 px-4 py-3 text-sm text-primary-800" role="status">{{ session('status') }}</div>
            @endif
            @if (session('error'))
                <div class="mt-4 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700" role="alert">{{ session('error') }}</div>
            @endif

            <div class="relative mt-6">
                <div wire:loading.delay.long wire:target="next,goTo,regenerate,reorder" class="absolute inset-0 z-10 grid place-items-start justify-center rounded-2xl bg-white/70 pt-24 backdrop-blur-[1px]">
                    <div class="flex items-center gap-3 rounded-full bg-white px-5 py-3 text-sm font-semibold text-primary-800 shadow-lg ring-1 ring-slate-200" role="status">
                        <svg class="size-5 animate-spin text-primary-700" viewBox="0 0 24 24" fill="none" aria-hidden="true"><circle cx="12" cy="12" r="10" stroke="currentColor" stroke-width="3" class="opacity-25" /><path d="M22 12a10 10 0 0 1-10 10" stroke="currentColor" stroke-width="3" /></svg>
                        Working on your plan…
                    </div>
                </div>

                @include('livewire.trip-builder.step-'.$step)
            </div>

            {{-- Back / Next --}}
            @if ($step < 8)
                <div class="mt-8 flex items-center justify-between gap-3 border-t border-slate-200 pt-6">
                    @if ($step > 1)
                        <button type="button" wire:click="back" class="btn btn-secondary">Back</button>
                    @else
                        <span></span>
                    @endif
                    <button type="button" wire:click="next" wire:loading.attr="disabled" wire:target="next" class="btn btn-primary px-6 py-3">
                        {{ $step === 5 ? 'Build my itinerary' : 'Next: '.$steps[$step + 1] }}
                        <x-icon name="arrow-right" class="size-4" />
                    </button>
                </div>
            @else
                <div class="mt-8 border-t border-slate-200 pt-6">
                    <button type="button" wire:click="back" class="btn btn-secondary">Back</button>
                </div>
            @endif
        </section>

        {{-- Live summary: sidebar on lg+, bottom sheet on mobile --}}
        <aside class="hidden lg:block" aria-label="Trip summary">
            <div class="sticky top-24">
                @include('livewire.trip-builder.summary')
            </div>
        </aside>
    </div>

    <div x-data="{ open: false }" class="fixed inset-x-0 bottom-0 z-30 lg:hidden">
        <div x-show="open" x-cloak x-transition.opacity class="fixed inset-0 bg-slate-900/40" @click="open = false"></div>
        <div class="relative rounded-t-2xl border-t border-slate-200 bg-white shadow-[0_-8px_24px_rgb(0_0_0/0.08)]">
            <button type="button" @click="open = ! open" :aria-expanded="open.toString()" aria-controls="summary-sheet"
                    class="flex w-full items-center justify-between gap-3 px-4 py-3 text-start">
                <span class="text-sm">
                    <span class="font-semibold">{{ $summary['tier'] ?? 'Your trip' }}</span>
                    <span class="text-slate-500">· {{ $summary['places'] }} {{ Str::plural('place', $summary['places']) }}@if ($summary['days']) · {{ $summary['days'] }} days @endif</span>
                </span>
                <span class="flex items-center gap-2 text-sm font-bold">
                    @if ($summary['total'])
                        {{ $summary['is_estimate_only'] ? 'from ' : '' }}${{ number_format((float) $summary['total']) }}
                    @endif
                    <x-icon name="chevron-down" class="size-4 rotate-180 transition" x-bind:class="open && 'rotate-0'" />
                </span>
            </button>
            <div id="summary-sheet" x-show="open" x-collapse x-cloak class="max-h-[60vh] overflow-y-auto px-4 pb-4">
                @include('livewire.trip-builder.summary')
            </div>
        </div>
    </div>
</div>
