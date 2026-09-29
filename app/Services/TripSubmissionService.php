<?php

namespace App\Services;

use App\Enums\PriceCategory;
use App\Enums\TripStatus;
use App\Enums\UserRole;
use App\Mail\NewTripRequest;
use App\Mail\TripSubmitted;
use App\Models\Place;
use App\Models\Trip;
use App\Models\User;
use App\Services\Itinerary\Itinerary;
use App\Services\Pricing\PriceBreakdown;
use App\Support\Money;
use App\Support\TripDraft;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

/**
 * Turns a builder draft into a submitted trip (SRS 8.4, FR-18, FR-19): one transaction that
 * writes the trip, days, stops, travellers, price lines and status history, then queues the
 * confirmation email and the new-request alert for agents.
 */
class TripSubmissionService
{
    public function __construct(private PricingService $pricing) {}

    /**
     * @param  array{full_name: string, email: string, phone?: ?string, whatsapp?: ?string, country?: ?string, age?: ?int, companions?: array}  $lead
     */
    public function submit(TripDraft $draft, array $lead, ?User $user = null): Trip
    {
        $itinerary = Itinerary::fromArray($draft->itinerary);
        $price = $this->pricing->breakdown($draft, $itinerary);

        $trip = DB::transaction(function () use ($draft, $lead, $user, $itinerary, $price) {
            $trip = $this->createTrip($draft, $user, $itinerary, $price);
            $this->copyDays($trip, $draft, $itinerary);
            $this->copyTravellers($trip, $lead);
            $this->copyPrice($trip, $price);

            $trip->categories()->sync($draft->categoryIds);
            $trip->cuisines()->sync($draft->cuisineIds);
            $trip->statusHistory()->create(['from_status' => null, 'to_status' => TripStatus::Submitted->value, 'changed_by' => $user?->id, 'note' => 'Submitted from the Trip Builder.']);

            return $trip;
        });

        $trip->load('leadTraveller', 'user');

        Mail::to($trip->contactEmail())->queue(new TripSubmitted($trip));

        $staff = User::whereIn('role', [UserRole::Agent->value, UserRole::Admin->value])->pluck('email')
            ->push(config('lankaguide.contact.admin_email'))->filter()->unique()->values();
        if ($staff->isNotEmpty()) {
            Mail::to($staff->all())->queue(new NewTripRequest($trip));
        }

        return $trip;
    }

    /**
     * LG360-{year}-{00001}, sequential per year.
     */
    public function nextReference(): string
    {
        $prefix = 'LG360-'.now()->year.'-';
        $last = Trip::where('reference', 'like', $prefix.'%')->lockForUpdate()->orderByDesc('reference')->value('reference');
        $next = $last ? ((int) Str::afterLast($last, '-')) + 1 : 1;

        return $prefix.str_pad((string) $next, 5, '0', STR_PAD_LEFT);
    }

    private function createTrip(TripDraft $draft, ?User $user, Itinerary $itinerary, PriceBreakdown $price): Trip
    {
        $attributes = [
            'user_id' => $user?->id,
            'tier' => $draft->tierEnum(),
            'start_date' => $draft->startDate,
            'days' => $itinerary->dayCount() ?: (int) $draft->days,
            'adults' => $draft->adults,
            'children' => $draft->children(),
            'infants' => $draft->infants,
            'children_ages' => $draft->childrenAges ? implode(',', $draft->childrenAges) : null,
            'luggage' => $draft->luggage,
            'arrival_point' => $draft->arrivalPoint,
            'departure_point' => $draft->departurePoint,
            'meal_plan' => $draft->mealPlan ?? 'BB',
            'dietary_notes' => $draft->dietaryNotes,
            'vehicle_id' => $draft->vehicleId,
            'guide_type' => $draft->guideType,
            'guide_id' => $draft->guideId,
            'guide_language' => $draft->guideLanguage,
            'special_requests' => $draft->specialRequests,
            'status' => TripStatus::Submitted,
            'estimated_total' => Money::decimal($price->total),
            'currency' => $price->currency,
            'access_token' => Str::random(40),
            'submitted_at' => now(),
            'data_consent_at' => now(),
        ];

        // A parallel submission can take the same reference: retry with the next number.
        for ($attempt = 0; $attempt < 3; $attempt++) {
            try {
                return Trip::create([...$attributes, 'reference' => $this->nextReference()]);
            } catch (QueryException $e) {
                if ($attempt === 2 || ! str_contains($e->getMessage(), 'reference')) {
                    throw $e;
                }
            }
        }

        throw new \RuntimeException('Could not create a trip reference.');
    }

    private function copyDays(Trip $trip, TripDraft $draft, Itinerary $itinerary): void
    {
        $rooms = (int) ceil(max(1, $draft->adults) / 2);
        $existingPlaces = Place::whereKey($itinerary->placeIds())->pluck('id')->flip();

        foreach ($itinerary->days as $day) {
            $choice = $draft->hotels[$day['number']] ?? null;

            $tripDay = $trip->tripDays()->create([
                'day_number' => $day['number'],
                'date' => $day['date'],
                'title' => Str::limit($day['title'], 150, ''),
                'overnight_town' => $day['overnight']['town'] ?? null,
                'hotel_id' => ! empty($day['overnight']) ? ($choice['hotel_id'] ?? null) : null,
                'room_rate_id' => ! empty($day['overnight']) ? ($choice['room_rate_id'] ?? null) : null,
                'rooms' => $rooms,
                'drive_km' => $day['drive_km'],
                'drive_minutes' => $day['drive_minutes'],
            ]);

            foreach (array_values($day['stops']) as $i => $stop) {
                if (! $existingPlaces->has($stop['place_id'])) {
                    continue;
                }

                $tripDay->stops()->create([
                    'place_id' => $stop['place_id'],
                    'sequence' => $i + 1,
                    'arrive_at' => $stop['arrive'],
                    'depart_at' => $stop['depart'],
                    'km_from_prev' => $stop['km_from_prev'],
                    'minutes_from_prev' => $stop['minutes_from_prev'],
                ]);
            }
        }
    }

    private function copyTravellers(Trip $trip, array $lead): void
    {
        $trip->travellers()->create([
            'full_name' => $lead['full_name'],
            'country' => $lead['country'] ?? null,
            'age' => $lead['age'] ?? null,
            'email' => $lead['email'],
            'phone' => $lead['phone'] ?? null,
            'whatsapp' => $lead['whatsapp'] ?? null,
            'is_lead' => true,
        ]);

        foreach ($lead['companions'] ?? [] as $companion) {
            if (filled($companion['name'] ?? null)) {
                $trip->travellers()->create(['full_name' => $companion['name'], 'age' => $companion['age'] ?? null, 'is_lead' => false]);
            }
        }
    }

    private function copyPrice(Trip $trip, PriceBreakdown $price): void
    {
        foreach ($price->lines as $line) {
            $trip->priceItems()->create([
                'category' => $line['category'],
                'description' => Str::limit($line['description'], 200, ''),
                'qty' => $line['qty'],
                'unit_price' => Money::decimal($line['unit_cents']),
                'amount' => Money::decimal($line['amount_cents']),
            ]);
        }

        if ($price->serviceFee) {
            $trip->priceItems()->create(['category' => PriceCategory::ServiceFee, 'description' => 'Service fee', 'qty' => 1, 'unit_price' => Money::decimal($price->serviceFee), 'amount' => Money::decimal($price->serviceFee)]);
        }
        if ($price->tax) {
            $trip->priceItems()->create(['category' => PriceCategory::Tax, 'description' => 'Tax', 'qty' => 1, 'unit_price' => Money::decimal($price->tax), 'amount' => Money::decimal($price->tax)]);
        }
    }
}
