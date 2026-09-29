@php
    use App\Enums\TripStatus;

    $mapDays = App\Support\TripMapData::fromTrip($trip);
    $total = $trip->displayTotal();
    $isFinal = $trip->final_total !== null;
    $history = $trip->statusHistory->filter(fn ($h) => $h->from_status !== $h->to_status || $h->from_status === null);
    $itemsByCategory = $trip->priceItems->groupBy(fn ($item) => $item->category->label());
    $end = $trip->start_date?->copy()->addDays(max($trip->days - 1, 0));
@endphp

<x-app-layout :title="'Trip '.$trip->reference" description="Your LankaGuide360 trip plan.">
    @push('head')
        <meta name="robots" content="noindex, nofollow">
        @vite(['resources/js/map.js'])
    @endpush

    <div class="mx-auto max-w-6xl px-4 py-8 sm:px-6 lg:px-8">
        @if ($submitted)
            <div class="mb-8 rounded-2xl border border-primary-200 bg-primary-50 p-6" role="status">
                <div class="flex items-start gap-4">
                    <span class="grid size-12 shrink-0 place-items-center rounded-full bg-primary-700 text-white"><x-icon name="check" class="size-6" /></span>
                    <div>
                        <h2 class="text-xl font-bold text-primary-900">Thank you! Your trip request has been sent.</h2>
                        <p class="mt-1 text-primary-900">Your reference is <strong class="font-mono">{{ $trip->reference }}</strong>. An agent will review your plan and usually replies within one working day.</p>
                        <p class="mt-2 text-sm text-primary-800">We've emailed you a private link to this page. Keep it safe: anyone with the link can see your trip.</p>
                    </div>
                </div>
            </div>
        @endif

        @foreach (['status' => 'border-primary-200 bg-primary-50 text-primary-800', 'warning' => 'border-accent-300 bg-accent-50 text-accent-700', 'error' => 'border-red-200 bg-red-50 text-red-700'] as $key => $classes)
            @if (session($key))
                <div class="mb-6 rounded-lg border px-4 py-3 text-sm {{ $classes }}" role="status">{{ session($key) }}</div>
            @endif
        @endforeach

        {{-- Heading --}}
        <div class="flex flex-col gap-4 md:flex-row md:items-start md:justify-between">
            <div>
                <p class="font-mono text-sm text-slate-500">{{ $trip->reference }}</p>
                <h1 class="mt-1 text-3xl font-extrabold tracking-tight">Your {{ $trip->days }}-day Sri Lanka trip</h1>
                <p class="mt-2 flex flex-wrap items-center gap-x-3 gap-y-1 text-slate-600">
                    <x-admin.status-badge :status="$trip->status" class="text-sm" />
                    <span>{{ $trip->start_date?->format('j M Y') }} – {{ $end?->format('j M Y') }}</span>
                    <span aria-hidden="true">·</span>
                    <span>{{ $trip->tier->label() }}</span>
                    <span aria-hidden="true">·</span>
                    <span>{{ $trip->adults }} {{ Str::plural('adult', $trip->adults) }}@if ($trip->children), {{ $trip->children }} {{ Str::plural('child', $trip->children) }}@endif @if ($trip->infants), {{ $trip->infants }} {{ Str::plural('infant', $trip->infants) }}@endif</span>
                </p>
            </div>
            <div class="flex flex-wrap gap-2">
                @if ($canDownload)
                    <a href="{{ route('trips.pdf', $trip) }}" class="btn btn-primary"><x-icon name="download" class="size-4" /> Download PDF</a>
                @endif
                @if ($canCancel)
                    <form method="POST" action="{{ route('trips.cancel', $trip) }}" onsubmit="return confirm('Cancel this trip request? This cannot be undone.')">
                        @csrf
                        <button type="submit" class="btn btn-secondary text-red-700">Cancel trip</button>
                    </form>
                @endif
            </div>
        </div>

        <div class="mt-8 grid gap-8 lg:grid-cols-3">
            <div class="space-y-8 lg:col-span-2">
                {{-- Map --}}
                <section aria-label="Route map">
                    <div class="h-80 overflow-hidden rounded-2xl border border-slate-200 bg-slate-100 sm:h-96" x-data="tripMap({ days: @js($mapDays) })"></div>
                </section>

                {{-- Day by day --}}
                <section aria-labelledby="days-heading">
                    <h2 id="days-heading" class="text-xl font-bold">Day by day</h2>
                    <ol class="mt-4 space-y-4">
                        @foreach ($trip->tripDays as $day)
                            <li class="rounded-2xl border border-slate-200 bg-white p-5" id="day-{{ $day->day_number }}">
                                <div class="flex flex-wrap items-baseline justify-between gap-2">
                                    <h3 class="font-semibold"><span class="text-primary-700">Day {{ $day->day_number }}</span> · {{ $day->title }}</h3>
                                    <p class="text-sm text-slate-500">{{ $day->date?->format('D j M') }}@if ((float) $day->drive_km > 0) · {{ number_format((float) $day->drive_km) }} km, about {{ intdiv($day->drive_minutes, 60) }}h {{ $day->drive_minutes % 60 }}m on the road @endif</p>
                                </div>
                                @if ($day->stops->isNotEmpty())
                                    <ol class="mt-3 space-y-2 border-s-2 border-primary-100 ps-4">
                                        @foreach ($day->stops as $stop)
                                            <li class="relative">
                                                <span class="absolute -start-[1.4rem] top-1.5 size-2.5 rounded-full bg-primary-600" aria-hidden="true"></span>
                                                <p class="text-sm">
                                                    @if ($stop->arrive_at)<span class="font-mono text-xs text-slate-500">{{ substr($stop->arrive_at, 0, 5) }}</span>@endif
                                                    @if ($stop->place)
                                                        <a href="{{ $stop->place->url() }}" class="font-medium text-slate-900 hover:text-primary-700">{{ $stop->place->name }}</a>
                                                        <span class="text-slate-500">· {{ $stop->place->district?->name }}</span>
                                                    @endif
                                                </p>
                                            </li>
                                        @endforeach
                                    </ol>
                                @else
                                    <p class="mt-2 text-sm text-slate-600">A free day to relax, or travel day.</p>
                                @endif
                                @if ($day->overnight_town || $day->hotel)
                                    <p class="mt-3 flex items-center gap-2 text-sm text-slate-600">
                                        <x-icon name="building" class="size-4 text-slate-400" />
                                        Overnight: {{ $day->hotel?->name ?? 'hotel to be confirmed' }}@if ($day->overnight_town), {{ $day->overnight_town }}@endif
                                        @if ($day->roomRate) <span class="text-slate-400">({{ $day->roomRate->room_type }})</span>@endif
                                    </p>
                                @endif
                            </li>
                        @endforeach
                    </ol>
                </section>

                {{-- Messages --}}
                <section aria-labelledby="messages-heading" id="messages" class="scroll-mt-20">
                    <h2 id="messages-heading" class="text-xl font-bold">Messages with your agent</h2>
                    <div class="mt-4 space-y-3">
                        @forelse ($trip->messages as $message)
                            @php $mine = $message->sender_role === App\Enums\SenderRole::Traveller; @endphp
                            <div @class(['max-w-[85%] rounded-2xl px-4 py-3 text-sm', 'ms-auto bg-primary-700 text-white' => $mine, 'bg-slate-100 text-slate-800' => ! $mine])>
                                <p class="whitespace-pre-line">{{ $message->body }}</p>
                                <p @class(['mt-1 text-xs', 'text-primary-100' => $mine, 'text-slate-500' => ! $mine])>
                                    {{ $mine ? 'You' : ($message->sender?->name ?? 'LankaGuide360') }} · {{ $message->created_at?->format('j M, H:i') }}
                                </p>
                            </div>
                        @empty
                            <p class="text-sm text-slate-600">No messages yet. Ask your agent anything about the plan.</p>
                        @endforelse
                    </div>
                    @if (! in_array($trip->status, [TripStatus::Cancelled, TripStatus::Completed], true))
                        <form method="POST" action="{{ route('trips.message', $trip) }}" class="mt-4">
                            @csrf
                            <label for="message-body" class="form-label">Write a message</label>
                            <textarea id="message-body" name="body" rows="3" required maxlength="3000" class="form-control">{{ old('body') }}</textarea>
                            @error('body')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                            <button type="submit" class="btn btn-primary mt-2">Send</button>
                        </form>
                    @endif
                </section>

                @if ($canReview)
                    <section aria-labelledby="review-heading" class="rounded-2xl border border-accent-200 bg-accent-50 p-6">
                        <h2 id="review-heading" class="text-xl font-bold">How was your trip?</h2>
                        <form method="POST" action="{{ route('trips.review', $trip) }}" class="mt-4 space-y-3" x-data="{ rating: {{ (int) old('rating', 5) }} }">
                            @csrf
                            <fieldset>
                                <legend class="form-label">Rating</legend>
                                <div class="flex gap-1">
                                    @for ($i = 1; $i <= 5; $i++)
                                        <label class="cursor-pointer">
                                            <input type="radio" name="rating" value="{{ $i }}" class="sr-only" x-model.number="rating">
                                            <span class="sr-only">{{ $i }} {{ Str::plural('star', $i) }}</span>
                                            <span :class="rating >= {{ $i }} ? 'text-accent-500' : 'text-slate-300'"><x-icon name="star" class="size-7 fill-current" /></span>
                                        </label>
                                    @endfor
                                </div>
                            </fieldset>
                            <div>
                                <label for="review-comment" class="form-label">Your review</label>
                                <textarea id="review-comment" name="comment" rows="4" required minlength="10" maxlength="2000" class="form-control">{{ old('comment') }}</textarea>
                                @error('comment')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                            </div>
                            <button type="submit" class="btn btn-primary">Send review</button>
                        </form>
                    </section>
                @endif
            </div>

            {{-- Sidebar: price and status --}}
            <aside class="space-y-6">
                <section class="rounded-2xl border border-slate-200 bg-white p-5" aria-labelledby="price-heading">
                    <h2 id="price-heading" class="font-semibold">{{ $isFinal ? 'Final price' : 'Estimated price' }}</h2>
                    <p class="mt-2 text-3xl font-extrabold">${{ number_format((float) $total, 2) }}</p>
                    <p class="text-sm text-slate-500">
                        {{ $trip->currency }} for {{ $trip->travellerCount() }} {{ Str::plural('traveller', $trip->travellerCount()) }}
                        @if ($isFinal && $trip->price_note)<br>{{ $trip->price_note }}@endif
                        @unless ($isFinal)<br>Your agent confirms the final price after checking availability.@endunless
                    </p>

                    @if ($trip->priceItems->isNotEmpty())
                        <details class="mt-4 text-sm">
                            <summary class="cursor-pointer font-medium text-primary-700">Price breakdown</summary>
                            <dl class="mt-3 space-y-3">
                                @foreach ($itemsByCategory as $category => $items)
                                    <div>
                                        <dt class="font-semibold text-slate-700">{{ $category }}</dt>
                                        @foreach ($items as $item)
                                            <dd class="flex justify-between gap-3 text-slate-600"><span>{{ $item->description }}</span><span class="whitespace-nowrap">${{ number_format((float) $item->amount, 2) }}</span></dd>
                                        @endforeach
                                    </div>
                                @endforeach
                            </dl>
                        </details>
                    @endif
                </section>

                <section class="rounded-2xl border border-slate-200 bg-white p-5 text-sm" aria-labelledby="details-heading">
                    <h2 id="details-heading" class="font-semibold">Trip details</h2>
                    <dl class="mt-3 space-y-2">
                        <div class="flex justify-between gap-3"><dt class="text-slate-500">Arrival</dt><dd class="text-end">{{ $trip->arrival_point }}</dd></div>
                        <div class="flex justify-between gap-3"><dt class="text-slate-500">Departure</dt><dd class="text-end">{{ $trip->departure_point }}</dd></div>
                        <div class="flex justify-between gap-3"><dt class="text-slate-500">Meals</dt><dd class="text-end">{{ $trip->meal_plan->label() }}</dd></div>
                        <div class="flex justify-between gap-3"><dt class="text-slate-500">Vehicle</dt><dd class="text-end">{{ $trip->vehicle?->type ?? 'To be confirmed' }}</dd></div>
                        <div class="flex justify-between gap-3"><dt class="text-slate-500">Guide</dt><dd class="text-end">{{ $trip->guide_type->label() }}@if ($trip->guide_type !== App\Enums\GuideType::None) ({{ $trip->guide_language }})@endif</dd></div>
                        @if ($trip->special_requests)
                            <div><dt class="text-slate-500">Special requests</dt><dd class="mt-1 whitespace-pre-line">{{ $trip->special_requests }}</dd></div>
                        @endif
                    </dl>
                </section>

                <section class="rounded-2xl border border-slate-200 bg-white p-5" aria-labelledby="history-heading">
                    <h2 id="history-heading" class="font-semibold">Status history</h2>
                    <ol class="mt-3 space-y-3 border-s-2 border-slate-100 ps-4 text-sm">
                        @foreach ($history as $entry)
                            <li>
                                <x-admin.status-badge :status="$entry->to_status" />
                                <span class="ms-1 text-xs text-slate-500">{{ $entry->created_at?->format('j M Y, H:i') }}</span>
                                @if ($entry->note && $entry->to_status !== 'under_review')
                                    <p class="mt-1 text-slate-600">{{ $entry->note }}</p>
                                @endif
                            </li>
                        @endforeach
                    </ol>
                </section>

                @guest
                    <p class="text-xs text-slate-500">Bookmark this page, or <a href="{{ route('register') }}" class="text-primary-700 underline">create an account</a> with the same email to see your trips in one place.</p>
                @endguest
            </aside>
        </div>
    </div>
</x-app-layout>
