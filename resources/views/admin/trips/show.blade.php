@php
    use App\Enums\GuideType;
    use App\Enums\MealPlan;
    use App\Enums\PriceCategory;
    use App\Enums\TripStatus;

    $lead = $trip->leadTraveller ?? $trip->travellers->firstWhere('is_lead', true);
    $companions = $trip->travellers->reject(fn ($t) => $t->is_lead);
    $whatsapp = preg_replace('/\D+/', '', (string) ($lead?->whatsapp ?: $lead?->phone));
    $next = collect($trip->status->nextStatuses())->reject(fn ($s) => $s === $trip->status || $s === TripStatus::Draft);
    $mapDays = App\Support\TripMapData::fromTrip($trip);
    $categoryOptions = collect(PriceCategory::cases())->mapWithKeys(fn ($c) => [$c->value => $c->label()]);
    $hotelOptions = function ($day) use ($hotelsByTown) {
        $hotels = collect($hotelsByTown[$day->overnight_town] ?? []);
        if ($day->hotel && ! $hotels->contains('id', $day->hotel->id)) {
            $hotels->prepend($day->hotel);
        }

        return $hotels->map(fn ($h) => [
            'id' => $h->id,
            'name' => $h->name.' ('.$h->tier->label().')',
            'rates' => $h->roomRates->map(fn ($r) => ['id' => $r->id, 'label' => $r->room_type.' · '.$r->meal_plan->value.' · $'.number_format((float) $r->price_per_night, 2).($r->is_estimate ? ' (estimate)' : '')])->values(),
        ])->values();
    };
@endphp

<x-admin-layout :title="'Trip '.$trip->reference">
    @push('head') @vite(['resources/js/map.js']) @endpush

    <x-admin.header :title="'Trip '.$trip->reference" :back="route('admin.trips.index')"
                    :subtitle="$trip->days.' days from '.$trip->start_date?->format('j M Y').' · '.$trip->tier->label().' · '.$trip->travellerCount().' travellers'">
        <x-admin.status-badge :status="$trip->status" class="text-sm" />
        <a href="{{ route('trips.pdf', $trip) }}" class="btn btn-secondary"><x-icon name="download" class="size-4" /> PDF</a>
        <a href="{{ route('trips.show', $trip) }}" class="btn btn-secondary" target="_blank" rel="noopener">Traveller view <x-icon name="external" class="size-4" /></a>
    </x-admin.header>

    <x-admin.flash />

    <div class="grid gap-6 xl:grid-cols-3">
        <div class="space-y-6 xl:col-span-2">
            {{-- Status actions --}}
            <section class="admin-card p-5" aria-labelledby="status-heading">
                <h3 id="status-heading" class="font-semibold">Status</h3>
                @if ($next->isEmpty())
                    <p class="mt-2 text-sm text-slate-600">This trip is {{ strtolower($trip->status->label()) }}; no further status changes.</p>
                @else
                    <form method="POST" action="{{ route('admin.trips.transition', $trip) }}" class="mt-3 grid gap-3 sm:grid-cols-2"
                          x-data="{ status: @js(old('status', $next->first()->value)) }">
                        @csrf
                        <x-admin.select name="status" label="Change status to" :options="$next->mapWithKeys(fn ($s) => [$s->value => $s->label()])" x-model="status" required />
                        <div x-show="status === 'approved'" x-cloak class="grid gap-3 sm:col-span-2 sm:grid-cols-2">
                            <x-admin.input name="final_total" type="number" step="0.01" min="0" label="Final price (USD)"
                                           :value="$trip->final_total ?? $trip->estimated_total"
                                           help="Price lines total: ${{ number_format($itemsTotal / 100, 2) }}" />
                            <x-admin.input name="price_note" label="Price note for the traveller" :value="$trip->price_note" help="Why it differs from the estimate, e.g. “peak-season hotel rates”." />
                        </div>
                        <div class="sm:col-span-2">
                            <x-admin.textarea name="note" label="Note to the traveller" rows="2" help="Emailed with the status change. Required when rejecting." />
                        </div>
                        <div class="sm:col-span-2">
                            <button type="submit" class="btn btn-primary">Update status and email the traveller</button>
                        </div>
                    </form>
                @endif
            </section>

            {{-- Map + days --}}
            <section class="admin-card p-5" aria-labelledby="days-heading">
                <h3 id="days-heading" class="font-semibold">Itinerary</h3>
                <div class="mt-3 h-72 overflow-hidden rounded-lg bg-slate-100" x-data="tripMap({ days: @js($mapDays) })"></div>

                <ol class="mt-4 space-y-4">
                    @foreach ($trip->tripDays as $day)
                        <li id="day-{{ $day->day_number }}" class="scroll-mt-20 rounded-lg border border-slate-200 p-4">
                            <div class="flex flex-wrap items-baseline justify-between gap-2">
                                <h4 class="font-semibold"><span class="text-primary-700">Day {{ $day->day_number }}</span> · {{ $day->date?->format('D j M') }} · {{ $day->title }}</h4>
                                <span class="text-xs text-slate-500">{{ number_format((float) $day->drive_km) }} km · {{ intdiv((int) $day->drive_minutes, 60) }}h {{ (int) $day->drive_minutes % 60 }}m driving</span>
                            </div>
                            <ul class="mt-2 divide-y divide-slate-100 text-sm">
                                @foreach ($day->stops as $stop)
                                    <li class="flex items-center justify-between gap-2 py-1.5">
                                        <span>
                                            <span class="font-mono text-xs text-slate-500">{{ $stop->arrive_at ? substr($stop->arrive_at, 0, 5) : '--:--' }}</span>
                                            {{ $stop->place?->name }} <span class="text-xs text-slate-500">{{ $stop->place?->district?->name }}</span>
                                        </span>
                                        <span class="flex shrink-0 items-center gap-1">
                                            @foreach (['up' => '↑', 'down' => '↓'] as $direction => $arrow)
                                                <form method="POST" action="{{ route('admin.trips.stops.move', $stop) }}">
                                                    @csrf
                                                    <input type="hidden" name="direction" value="{{ $direction }}">
                                                    <button type="submit" class="btn btn-secondary btn-sm" aria-label="Move {{ $stop->place?->name }} {{ $direction }}">{{ $arrow }}</button>
                                                </form>
                                            @endforeach
                                            <form method="POST" action="{{ route('admin.trips.stops.destroy', $stop) }}" onsubmit="return confirm('Remove this stop?')">
                                                @csrf @method('DELETE')
                                                <button type="submit" class="btn-link-danger text-xs">Remove</button>
                                            </form>
                                        </span>
                                    </li>
                                @endforeach
                            </ul>
                            <form method="POST" action="{{ route('admin.trips.stops.store', $day) }}" class="mt-2 flex gap-2">
                                @csrf
                                <label for="add-stop-{{ $day->id }}" class="sr-only">Add a place to day {{ $day->day_number }}</label>
                                <select id="add-stop-{{ $day->id }}" name="place_id" class="form-control py-1 text-sm" required>
                                    <option value="">Add a place…</option>
                                    @foreach ($places as $place)
                                        <option value="{{ $place->id }}">{{ $place->name }}</option>
                                    @endforeach
                                </select>
                                <button type="submit" class="btn btn-secondary btn-sm">Add</button>
                            </form>
                        </li>
                    @endforeach
                </ol>
            </section>

            {{-- Plan details: hotels, vehicle, guide, meals --}}
            <section class="admin-card p-5" aria-labelledby="plan-heading">
                <h3 id="plan-heading" class="font-semibold">Hotels, transport and guide</h3>
                <form method="POST" action="{{ route('admin.trips.update', $trip) }}" class="mt-3 space-y-4">
                    @csrf @method('PUT')
                    <div class="overflow-x-auto">
                        <table class="admin-table">
                            <thead><tr><th>Night</th><th>Town</th><th>Hotel</th><th>Room and rate</th></tr></thead>
                            <tbody class="divide-y divide-slate-100">
                                @foreach ($trip->tripDays as $day)
                                    @continue($day->day_number === $trip->days && ! $day->hotel_id && ! $day->overnight_town)
                                    <tr x-data="{ hotels: @js($hotelOptions($day)), hotel: @js((string) $day->hotel_id), rate: @js((string) $day->room_rate_id), get rates() { return (this.hotels.find(h => String(h.id) === this.hotel) || { rates: [] }).rates } }">
                                        <td class="text-xs">{{ $day->day_number }}<br>{{ $day->date?->format('j M') }}</td>
                                        <td><input type="text" name="days[{{ $day->id }}][overnight_town]" value="{{ $day->overnight_town }}" class="form-control py-1 text-sm" aria-label="Overnight town, night {{ $day->day_number }}"></td>
                                        <td>
                                            <select name="days[{{ $day->id }}][hotel_id]" x-model="hotel" @change="rate = ''" class="form-control py-1 text-sm" aria-label="Hotel, night {{ $day->day_number }}">
                                                <option value="">To be confirmed</option>
                                                <template x-for="h in hotels" :key="h.id"><option :value="String(h.id)" x-text="h.name" :selected="String(h.id) === hotel"></option></template>
                                            </select>
                                        </td>
                                        <td>
                                            <select name="days[{{ $day->id }}][room_rate_id]" x-model="rate" class="form-control py-1 text-sm" aria-label="Room rate, night {{ $day->day_number }}">
                                                <option value="">—</option>
                                                <template x-for="r in rates" :key="r.id"><option :value="String(r.id)" x-text="r.label" :selected="String(r.id) === rate"></option></template>
                                            </select>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                        <p class="mt-1 text-xs text-slate-500">Hotels listed are the published hotels in each overnight town. Change the town and save to see other hotels.</p>
                    </div>

                    <div class="grid gap-4 sm:grid-cols-2">
                        <x-admin.select name="vehicle_id" label="Vehicle" placeholder="To be confirmed" :value="$trip->vehicle_id"
                                        :options="$vehicles->mapWithKeys(fn ($v) => [$v->id => $v->type.' ('.$v->tier->label().', '.$v->min_pax.'–'.$v->max_pax.' pax)'])" />
                        <x-admin.select name="meal_plan" label="Meal plan" :value="$trip->meal_plan" required
                                        :options="collect(MealPlan::cases())->mapWithKeys(fn ($m) => [$m->value => $m->label()])" />
                        <x-admin.select name="guide_type" label="Guide type" :value="$trip->guide_type" required
                                        :options="collect(GuideType::cases())->mapWithKeys(fn ($g) => [$g->value => $g->label()])" />
                        <x-admin.select name="guide_id" label="Guide" placeholder="Not assigned" :value="$trip->guide_id"
                                        :options="$guides->mapWithKeys(fn ($g) => [$g->id => $g->name.' ('.$g->type->label().', '.implode(', ', $g->languages ?? []).')'])" />
                        <x-admin.input name="guide_language" label="Guide language" :value="$trip->guide_language" required />
                    </div>
                    <x-admin.textarea name="internal_notes" label="Internal notes" :value="$trip->internal_notes" rows="3" help="Only agents and admins see these notes." />
                    <button type="submit" class="btn btn-primary">Save plan details</button>
                </form>
            </section>

            {{-- Price lines --}}
            <section id="price" class="admin-card scroll-mt-20 p-5" aria-labelledby="price-heading">
                <div class="flex flex-wrap items-center justify-between gap-2">
                    <h3 id="price-heading" class="font-semibold">Price lines</h3>
                    <form method="POST" action="{{ route('admin.trips.recalculate', $trip) }}">
                        @csrf
                        <button type="submit" class="btn btn-secondary btn-sm">Use lines total as final price</button>
                    </form>
                </div>
                <div class="mt-3 overflow-x-auto">
                    <table class="admin-table">
                        <thead><tr><th>Category</th><th>Description</th><th class="w-20">Qty</th><th class="w-28">Unit $</th><th class="text-end">Amount</th><th><span class="sr-only">Actions</span></th></tr></thead>
                        <tbody class="divide-y divide-slate-100">
                            @foreach ($trip->priceItems as $item)
                                <tr>
                                    <td>
                                        <select name="category" form="item-{{ $item->id }}" class="form-control py-1 text-sm" aria-label="Category">
                                            @foreach ($categoryOptions as $value => $label)
                                                <option value="{{ $value }}" @selected($item->category->value === $value)>{{ $label }}</option>
                                            @endforeach
                                        </select>
                                    </td>
                                    <td><input name="description" form="item-{{ $item->id }}" value="{{ $item->description }}" class="form-control py-1 text-sm" aria-label="Description" required maxlength="200"></td>
                                    <td><input name="qty" type="number" step="0.01" min="0" form="item-{{ $item->id }}" value="{{ (float) $item->qty }}" class="form-control py-1 text-sm" aria-label="Quantity" required></td>
                                    <td><input name="unit_price" type="number" step="0.01" form="item-{{ $item->id }}" value="{{ $item->unit_price }}" class="form-control py-1 text-sm" aria-label="Unit price" required></td>
                                    <td class="text-end whitespace-nowrap">${{ number_format((float) $item->amount, 2) }}</td>
                                    <td class="whitespace-nowrap text-end">
                                        <form id="item-{{ $item->id }}" method="POST" action="{{ route('admin.trips.items.update', $item) }}" class="inline">@csrf @method('PUT')<button type="submit" class="btn btn-secondary btn-sm">Save</button></form>
                                        <form method="POST" action="{{ route('admin.trips.items.destroy', $item) }}" class="inline" onsubmit="return confirm('Delete this line?')">@csrf @method('DELETE')<button type="submit" class="btn-link-danger text-xs">Delete</button></form>
                                    </td>
                                </tr>
                            @endforeach
                            <tr class="bg-slate-50">
                                <td>
                                    <select name="category" form="item-new" class="form-control py-1 text-sm" aria-label="New line category">
                                        @foreach ($categoryOptions as $value => $label)
                                            <option value="{{ $value }}">{{ $label }}</option>
                                        @endforeach
                                    </select>
                                </td>
                                <td><input name="description" form="item-new" placeholder="New line, e.g. Whale watching" class="form-control py-1 text-sm" aria-label="New line description" required maxlength="200"></td>
                                <td><input name="qty" type="number" step="0.01" min="0" value="1" form="item-new" class="form-control py-1 text-sm" aria-label="New line quantity" required></td>
                                <td><input name="unit_price" type="number" step="0.01" form="item-new" class="form-control py-1 text-sm" aria-label="New line unit price" required></td>
                                <td></td>
                                <td class="text-end"><form id="item-new" method="POST" action="{{ route('admin.trips.items.store', $trip) }}">@csrf<button type="submit" class="btn btn-primary btn-sm">Add</button></form></td>
                            </tr>
                        </tbody>
                        <tfoot class="text-sm">
                            <tr><td colspan="4" class="text-end">Lines total</td><td class="text-end font-semibold">${{ number_format($itemsTotal / 100, 2) }}</td><td></td></tr>
                            <tr><td colspan="4" class="text-end text-slate-500">Estimate at submission</td><td class="text-end text-slate-500">${{ number_format((float) $trip->estimated_total, 2) }}</td><td></td></tr>
                            <tr><td colspan="4" class="text-end">Final price</td><td class="text-end font-bold">{{ $trip->final_total !== null ? '$'.number_format((float) $trip->final_total, 2) : 'not set' }}</td><td></td></tr>
                        </tfoot>
                    </table>
                    <p class="mt-1 text-xs text-slate-500">Use a negative unit price for discounts. Service fee and tax lines are not recalculated automatically after edits.</p>
                </div>
            </section>

            {{-- Messages --}}
            <section id="messages" class="admin-card scroll-mt-20 p-5" aria-labelledby="messages-heading">
                <h3 id="messages-heading" class="font-semibold">Messages with the traveller</h3>
                <div class="mt-3 space-y-3">
                    @forelse ($trip->messages as $message)
                        @php $fromAgent = $message->sender_role === App\Enums\SenderRole::Agent; @endphp
                        <div @class(['max-w-[85%] rounded-xl px-4 py-2 text-sm', 'ms-auto bg-primary-700 text-white' => $fromAgent, 'bg-slate-100' => ! $fromAgent])>
                            <p class="whitespace-pre-line">{{ $message->body }}</p>
                            <p @class(['mt-1 text-xs', 'text-primary-100' => $fromAgent, 'text-slate-500' => ! $fromAgent])>{{ $message->sender?->name ?? $trip->contactName() }} · {{ $message->created_at?->format('j M, H:i') }}</p>
                        </div>
                    @empty
                        <p class="text-sm text-slate-500">No messages yet.</p>
                    @endforelse
                </div>
                <form method="POST" action="{{ route('admin.trips.message', $trip) }}" class="mt-4">
                    @csrf
                    <x-admin.textarea name="body" label="Reply" rows="3" required />
                    <button type="submit" class="btn btn-primary mt-2">Send to traveller</button>
                </form>
            </section>
        </div>

        {{-- Sidebar --}}
        <aside class="space-y-6">
            <section class="admin-card p-5 text-sm" aria-labelledby="contact-heading">
                <h3 id="contact-heading" class="font-semibold">Traveller</h3>
                @if ($lead)
                    <p class="mt-2 font-medium">{{ $lead->full_name }}</p>
                    <p class="text-slate-500">{{ $lead->country }}@if ($lead->age), {{ $lead->age }} years @endif · {{ $trip->user ? 'Registered user' : 'Guest' }}</p>
                    <div class="mt-3 flex flex-wrap gap-2">
                        @if ($lead->email)<a href="mailto:{{ $lead->email }}?subject={{ rawurlencode('Your trip '.$trip->reference) }}" class="btn btn-secondary btn-sm"><x-icon name="envelope" class="size-4" /> Email</a>@endif
                        @if ($lead->phone)<a href="tel:{{ preg_replace('/[^\d+]/', '', $lead->phone) }}" class="btn btn-secondary btn-sm"><x-icon name="phone" class="size-4" /> Call</a>@endif
                        @if ($whatsapp)<a href="https://wa.me/{{ $whatsapp }}" target="_blank" rel="noopener" class="btn btn-secondary btn-sm"><x-icon name="chat" class="size-4" /> WhatsApp</a>@endif
                    </div>
                    <dl class="mt-3 space-y-1 text-xs">
                        <div><dt class="inline text-slate-500">Email:</dt> <dd class="inline break-all">{{ $lead->email }}</dd></div>
                        <div><dt class="inline text-slate-500">Phone:</dt> <dd class="inline">{{ $lead->phone }}</dd></div>
                        @if ($lead->whatsapp)<div><dt class="inline text-slate-500">WhatsApp:</dt> <dd class="inline">{{ $lead->whatsapp }}</dd></div>@endif
                    </dl>
                @endif
                @if ($companions->isNotEmpty())
                    <h4 class="mt-4 text-xs font-semibold uppercase text-slate-500">Companions</h4>
                    <ul class="mt-1 text-xs">
                        @foreach ($companions as $companion)
                            <li>{{ $companion->full_name }}@if ($companion->age !== null) ({{ $companion->age }})@endif</li>
                        @endforeach
                    </ul>
                @endif
            </section>

            <section class="admin-card p-5 text-sm" aria-labelledby="facts-heading">
                <h3 id="facts-heading" class="font-semibold">Request</h3>
                <dl class="mt-2 space-y-1">
                    <div class="flex justify-between gap-2"><dt class="text-slate-500">Travellers</dt><dd>{{ $trip->adults }}A · {{ $trip->children }}C @if ($trip->children_ages)({{ $trip->children_ages }})@endif · {{ $trip->infants }}I</dd></div>
                    <div class="flex justify-between gap-2"><dt class="text-slate-500">Luggage</dt><dd>{{ $trip->luggage ?? '—' }} bags</dd></div>
                    <div class="flex justify-between gap-2"><dt class="text-slate-500">Arrival</dt><dd class="text-end">{{ $trip->arrival_point }}</dd></div>
                    <div class="flex justify-between gap-2"><dt class="text-slate-500">Departure</dt><dd class="text-end">{{ $trip->departure_point }}</dd></div>
                    <div class="flex justify-between gap-2"><dt class="text-slate-500">Interests</dt><dd class="text-end">{{ $trip->categories->pluck('name')->join(', ') ?: '—' }}</dd></div>
                    <div class="flex justify-between gap-2"><dt class="text-slate-500">Cuisines</dt><dd class="text-end">{{ $trip->cuisines->pluck('name')->join(', ') ?: '—' }}</dd></div>
                    @if ($trip->dietary_notes)<div><dt class="text-slate-500">Dietary notes</dt><dd>{{ $trip->dietary_notes }}</dd></div>@endif
                    @if ($trip->special_requests)<div><dt class="text-slate-500">Special requests</dt><dd class="whitespace-pre-line">{{ $trip->special_requests }}</dd></div>@endif
                    <div class="flex justify-between gap-2"><dt class="text-slate-500">Submitted</dt><dd>{{ $trip->submitted_at?->format('j M Y, H:i') }}</dd></div>
                </dl>
            </section>

            <section class="admin-card p-5 text-sm" aria-labelledby="assign-heading">
                <h3 id="assign-heading" class="font-semibold">Assigned agent</h3>
                <form method="POST" action="{{ route('admin.trips.assign', $trip) }}" class="mt-2 flex gap-2">
                    @csrf
                    <label for="agent_id" class="sr-only">Agent</label>
                    <select id="agent_id" name="agent_id" class="form-control py-1 text-sm">
                        <option value="">Unassigned</option>
                        @foreach ($agents as $agent)
                            <option value="{{ $agent->id }}" @selected($trip->agent_id === $agent->id)>{{ $agent->name }}</option>
                        @endforeach
                    </select>
                    <button type="submit" class="btn btn-secondary btn-sm">Save</button>
                </form>
            </section>

            @can('create', App\Models\Package::class)
                <section class="admin-card p-5 text-sm" aria-labelledby="package-heading">
                    <h3 id="package-heading" class="font-semibold">Save as package</h3>
                    <p class="mt-1 text-xs text-slate-500">Copies the days and places (never the traveller's details) into a new package.</p>
                    <form method="POST" action="{{ route('admin.trips.package', $trip) }}" class="mt-2 flex gap-2">
                        @csrf
                        <label for="package-name" class="sr-only">Package name</label>
                        <input id="package-name" name="name" required maxlength="150" placeholder="Package name" class="form-control py-1 text-sm">
                        <button type="submit" class="btn btn-secondary btn-sm">Save</button>
                    </form>
                </section>
            @endcan

            <section class="admin-card p-5 text-sm" aria-labelledby="history-heading">
                <h3 id="history-heading" class="font-semibold">History</h3>
                <ol class="mt-3 space-y-3 border-s-2 border-slate-100 ps-4">
                    @foreach ($trip->statusHistory->reverse() as $entry)
                        <li>
                            <div class="flex flex-wrap items-center gap-1">
                                @if ($entry->from_status !== $entry->to_status)<x-admin.status-badge :status="$entry->to_status" />@endif
                                <span class="text-xs text-slate-500">{{ $entry->created_at?->format('j M Y, H:i') }} · {{ $entry->changedBy?->name ?? 'Traveller' }}</span>
                            </div>
                            @if ($entry->note)<p class="mt-1 text-slate-600">{{ $entry->note }}</p>@endif
                        </li>
                    @endforeach
                </ol>
            </section>
        </aside>
    </div>
</x-admin-layout>
