{{-- Warnings and dropped places from the itinerary engine (SRS 5.3 step 6). --}}
@if ($itinerary->warnings || $itinerary->dropped)
    <div class="mb-5 space-y-2 rounded-2xl border border-accent-300 bg-accent-50 p-4 text-sm text-accent-800" role="status">
        @foreach ($itinerary->warnings as $warning)
            <p>{{ $warning }}</p>
        @endforeach
        @if ($itinerary->dropped)
            <ul class="list-disc ps-5">
                @foreach ($itinerary->dropped as $dropped)
                    <li><strong>{{ $dropped['name'] }}</strong>: {{ $dropped['reason'] }}</li>
                @endforeach
            </ul>
            <p>Add days in <button type="button" wire:click="goTo(5)" class="font-semibold underline">step 5</button> or remove places in <button type="button" wire:click="goTo(4)" class="font-semibold underline">step 4</button>.</p>
        @endif
    </div>
@endif
