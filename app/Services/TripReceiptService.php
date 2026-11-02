<?php

namespace App\Services;

use App\Enums\TripStatus;
use App\Enums\UserRole;
use App\Mail\TripAccepted;
use App\Mail\TripReceiptIssued;
use App\Models\Setting;
use App\Models\Trip;
use App\Models\TripDay;
use App\Models\TripPriceItem;
use App\Models\TripReceipt;
use App\Models\TripStop;
use App\Models\User;
use App\Support\Money;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use InvalidArgumentException;

/**
 * The end of the booking: the traveller accepts the approved plan and price, the agent sets the
 * final time at every place, and an e-receipt (booking voucher with a QR check) is issued and
 * emailed. Issued receipts keep a frozen snapshot of what was confirmed.
 */
class TripReceiptService
{
    /** Statuses in which an agent can finalize the timings and issue the receipt. */
    public const ISSUABLE = [TripStatus::Confirmed, TripStatus::InProgress, TripStatus::Completed];

    public function __construct(private TripWorkflow $workflow) {}

    /**
     * Traveller accepts the agent's final price and plan: Approved → Confirmed.
     */
    public function accept(Trip $trip, ?User $traveller): Trip
    {
        if ($trip->status !== TripStatus::Approved) {
            throw new InvalidArgumentException('Only an approved trip can be accepted.');
        }

        $this->workflow->transition($trip, TripStatus::Confirmed, $traveller, 'Booking accepted by the traveller.', ['accepted_at' => now()]);

        $recipients = $trip->agent
            ? [$trip->agent->email]
            : User::whereIn('role', [UserRole::Agent->value, UserRole::Admin->value])->pluck('email')->all();
        if ($recipients) {
            Mail::to($recipients)->queue(new TripAccepted($trip->fresh(['leadTraveller', 'agent'])));
        }

        return $trip;
    }

    /**
     * Save the final times and issue (or re-issue) the e-receipt.
     *
     * @param  array<int|string, array{arrive_at?: ?string, depart_at?: ?string}>  $times  keyed by trip_stop id
     * @param  array{paid_usd?: ?string, payment_method?: ?string, payment_note?: ?string}  $payment
     */
    public function finalize(Trip $trip, array $times, array $payment, User $agent): TripReceipt
    {
        if (! in_array($trip->status, self::ISSUABLE, true)) {
            throw new InvalidArgumentException('The traveller has to accept the booking before the e-receipt can be issued.');
        }

        $receipt = DB::transaction(function () use ($trip, $times, $payment, $agent) {
            $stops = TripStop::whereIn('trip_day_id', $trip->tripDays()->pluck('id'))->get()->keyBy('id');
            foreach ($times as $stopId => $time) {
                $stop = $stops->get((int) $stopId);
                if ($stop) {
                    $stop->forceFill([
                        'arrive_at' => $time['arrive_at'] ?? $stop->arrive_at,
                        'depart_at' => $time['depart_at'] ?? $stop->depart_at,
                    ])->save();
                }
            }

            $trip->forceFill(['itinerary_finalized_at' => now()])->save();
            $trip->load(['tripDays.stops.place.district', 'tripDays.hotel', 'tripDays.roomRate', 'travellers', 'priceItems', 'vehicle', 'guide', 'agent']);

            $currency = Money::displayCurrency();
            $values = [
                'issued_by' => $agent->id,
                'issued_at' => now(),
                'currency' => $currency,
                'exchange_rate' => match ($currency) {
                    'LKR' => (string) Setting::get('usd_lkr', '300'),
                    'EUR' => (string) Setting::get('usd_eur', '0.92'),
                    default => '1',
                },
                'total_usd' => $trip->displayTotal() ?? '0',
                'paid_usd' => $payment['paid_usd'] ?? '0',
                'payment_method' => $payment['payment_method'] ?? null,
                'payment_note' => $payment['payment_note'] ?? null,
                'snapshot' => $this->snapshot($trip),
            ];

            $receipt = $trip->receipt()->first();
            if ($receipt) {
                $receipt->forceFill([...$values, 'revision' => $receipt->revision + 1])->save();
            } else {
                $receipt = $this->createWithNumber($trip, $values);
            }

            $trip->statusHistory()->create([
                'from_status' => $trip->status->value,
                'to_status' => $trip->status->value,
                'changed_by' => $agent->id,
                'note' => "Timings finalized and e-receipt {$receipt->number} issued".($receipt->revision > 1 ? " (revision {$receipt->revision})" : '').'.',
            ]);

            return $receipt;
        });

        if ($trip->contactEmail()) {
            Mail::to($trip->contactEmail())->queue(new TripReceiptIssued($receipt->fresh('trip')));
        }

        return $receipt;
    }

    /**
     * Frozen copy of the confirmed booking for the receipt.
     *
     * @return array<string, mixed>
     */
    public function snapshot(Trip $trip): array
    {
        return [
            'reference' => $trip->reference,
            'tier' => $trip->tier->label(),
            'start_date' => $trip->start_date?->toDateString(),
            'end_date' => $trip->start_date?->copy()->addDays(max($trip->days - 1, 0))->toDateString(),
            'days' => $trip->days,
            'arrival_point' => $trip->arrival_point,
            'departure_point' => $trip->departure_point,
            'adults' => $trip->adults,
            'children' => $trip->children,
            'infants' => $trip->infants,
            'travellers' => $trip->travellers->sortByDesc('is_lead')->map(fn ($t) => ['name' => $t->full_name, 'lead' => (bool) $t->is_lead, 'country' => $t->country])->values()->all(),
            'contact' => ['email' => $trip->contactEmail(), 'phone' => $trip->leadTraveller?->phone],
            'meal_plan' => $trip->meal_plan->label(),
            'vehicle' => $trip->vehicle ? $trip->vehicleLabel() : null,
            'guide' => $trip->guide_type->label().($trip->guide?->name ? ' — '.$trip->guide->name : '').' ('.$trip->guide_language.')',
            'agent' => $trip->agent?->name,
            'itinerary' => $trip->tripDays->map(fn (TripDay $day) => [
                'day' => $day->day_number,
                'date' => $day->date?->toDateString(),
                'title' => $day->title,
                'stops' => $day->stops->map(fn (TripStop $stop) => [
                    'place' => $stop->place?->name,
                    'district' => $stop->place?->district?->name,
                    'arrive' => $stop->arrive_at ? substr($stop->arrive_at, 0, 5) : null,
                    'depart' => $stop->depart_at ? substr($stop->depart_at, 0, 5) : null,
                ])->values()->all(),
                'hotel' => $day->hotel?->name,
                'town' => $day->overnight_town,
                'room' => $day->roomRate?->room_type,
            ])->values()->all(),
            'price_lines' => $trip->priceItems->map(fn (TripPriceItem $item) => [
                'category' => $item->category->label(),
                'description' => $item->description,
                'amount_usd' => $item->amount,
            ])->values()->all(),
            'price_note' => $trip->price_note,
        ];
    }

    /**
     * LG360-R-{year}-{00001}, sequential per year (retry if two issue at the same moment).
     *
     * @param  array<string, mixed>  $values
     */
    private function createWithNumber(Trip $trip, array $values): TripReceipt
    {
        $prefix = 'LG360-R-'.now()->year.'-';

        for ($attempt = 0; ; $attempt++) {
            $last = TripReceipt::where('number', 'like', $prefix.'%')->lockForUpdate()->orderByDesc('number')->value('number');
            $next = $last ? ((int) Str::afterLast($last, '-')) + 1 : 1;

            try {
                return TripReceipt::create([
                    ...$values,
                    'trip_id' => $trip->id,
                    'number' => $prefix.str_pad((string) $next, 5, '0', STR_PAD_LEFT),
                    'verification_code' => Str::lower(Str::random(16)),
                ]);
            } catch (QueryException $e) {
                if ($attempt >= 2 || ! str_contains($e->getMessage(), 'number')) {
                    throw $e;
                }
            }
        }
    }
}
