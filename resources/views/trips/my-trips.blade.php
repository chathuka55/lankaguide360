<x-app-layout title="My Trips">
    <x-slot name="header">
        <h1 class="text-2xl font-bold">My Trips</h1>
        <p class="mt-1 text-sm text-slate-600">Your submitted trip plans, with their status and messages from your agent.</p>
    </x-slot>

    <div class="mx-auto max-w-5xl px-4 py-8 sm:px-6">
        @foreach (['status' => 'border-primary-200 bg-primary-50 text-primary-800', 'warning' => 'border-accent-300 bg-accent-50 text-accent-700'] as $key => $classes)
            @if (session($key))
                <div class="mb-6 rounded-lg border px-4 py-3 text-sm {{ $classes }}" role="status">{{ session($key) }}</div>
            @endif
        @endforeach

        @if ($trips->isEmpty())
            <div class="py-12 text-center">
                <div class="mx-auto grid size-14 place-items-center rounded-full bg-primary-50 text-primary-700">
                    <x-icon name="map" class="size-7" />
                </div>
                <h2 class="mt-4 text-xl font-bold">No trips yet</h2>
                <p class="mt-2 text-slate-600">When you submit a trip plan it will appear here with its status.</p>
                <a href="{{ route('plan') }}" class="btn btn-primary mt-6">Plan a trip</a>
            </div>
        @else
            <div class="mb-4 flex justify-end">
                <a href="{{ route('plan') }}" class="btn btn-primary">Plan another trip</a>
            </div>
            <ul class="grid gap-4 sm:grid-cols-2">
                @foreach ($trips as $trip)
                    <li>
                        <a href="{{ route('trips.show', $trip) }}" class="block rounded-2xl border border-slate-200 bg-white p-5 transition hover:border-primary-300 hover:shadow-md">
                            <div class="flex items-start justify-between gap-3">
                                <div>
                                    <p class="font-mono text-xs text-slate-500">{{ $trip->reference }}</p>
                                    <h2 class="mt-1 font-semibold">{{ $trip->days }}-day {{ $trip->tier->label() }} trip</h2>
                                </div>
                                <x-admin.status-badge :status="$trip->status" />
                            </div>
                            <p class="mt-2 text-sm text-slate-600">
                                From {{ $trip->start_date?->format('j M Y') }} · {{ $trip->travellerCount() }} {{ Str::plural('traveller', $trip->travellerCount()) }}
                            </p>
                            <div class="mt-3 flex items-center justify-between text-sm">
                                <span class="font-semibold">${{ number_format((float) $trip->displayTotal(), 2) }} <span class="font-normal text-slate-500">{{ $trip->final_total !== null ? 'final' : 'estimate' }}</span></span>
                                @if ($trip->unread_count)
                                    <span class="inline-flex items-center gap-1 rounded-full bg-accent-100 px-2 py-0.5 text-xs font-semibold text-accent-700">
                                        <x-icon name="chat" class="size-3.5" /> {{ $trip->unread_count }} new {{ Str::plural('message', $trip->unread_count) }}
                                    </span>
                                @endif
                            </div>
                        </a>
                    </li>
                @endforeach
            </ul>
        @endif
    </div>
</x-app-layout>
