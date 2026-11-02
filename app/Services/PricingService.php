<?php

namespace App\Services;

use App\Enums\GuideType;
use App\Enums\MealPlan;
use App\Enums\PriceCategory;
use App\Models\Guide;
use App\Models\Hotel;
use App\Models\Place;
use App\Models\RoomRate;
use App\Models\Setting;
use App\Models\Vehicle;
use App\Services\Itinerary\Itinerary;
use App\Services\Pricing\PriceBreakdown;
use App\Support\Money;
use App\Support\TripDraft;

/**
 * Trip price estimate (SRS 5.4):
 *
 *   Σ(room rate × rooms) + vehicle day rate × D + km rate × km + guide rate × D
 *   + meals not in the room rate + Σ tickets + activities + service fee % + tax %
 *
 * Every rate comes from the database or the settings table; nothing is hard-coded.
 * Rooms = ceil(adults ÷ 2), extra beds for anyone over the room occupancy (infants free).
 * The result is an estimate: an agent confirms the final price.
 */
class PricingService
{
    /** Meals included by each plan. */
    private const MEALS = [
        'RO' => [],
        'BB' => ['breakfast'],
        'HB' => ['breakfast', 'dinner'],
        'FB' => ['breakfast', 'lunch', 'dinner'],
        'AI' => ['breakfast', 'lunch', 'dinner'],
    ];

    public function __construct(private SuggestionService $suggestions) {}

    public function breakdown(TripDraft $draft, ?Itinerary $itinerary = null): PriceBreakdown
    {
        $itinerary ??= Itinerary::fromArray($draft->itinerary);
        $tier = $draft->tierEnum();
        $days = max(1, $itinerary->dayCount() ?: (int) $draft->days);
        $paying = max(1, $draft->adults + $draft->children());
        $price = new PriceBreakdown(Setting::get('currency', 'USD'), $paying);

        $this->accommodation($price, $draft, $itinerary);
        $this->transport($price, $draft, $days, $itinerary->totalKm());
        $this->guide($price, $draft, $days);
        $this->meals($price, $draft, $itinerary, $days);
        $this->tickets($price, $draft, $itinerary);
        $this->localDiscount($price, $draft);

        $local = $draft->isLocal();
        $price->subtotal = array_sum(array_column($price->lines, 'amount_cents'));
        $price->serviceFee = Money::percent($price->subtotal, Setting::get("service_fee_{$tier->value}", '0'));
        $price->tax = Money::percent($price->subtotal + $price->serviceFee, Setting::get($local ? 'tax_percent_local' : 'tax_percent', '0'));
        $price->notes[] = $local
            ? 'Sri Lankan resident prices: local entry tickets'.((float) Setting::get('local_discount_percent', '0') > 0 ? ' and a resident discount' : '').' are applied. Bring your NIC.'
            : 'Foreign traveller prices include applicable Sri Lanka Tourism Development Authority taxes and service charges.';
        $price->total = $price->subtotal + $price->serviceFee + $price->tax;
        $price->perPerson = (int) round($price->total / $paying);
        $price->totalLkr = (int) round($price->total * (float) Setting::get('usd_lkr', '0'));

        return $price;
    }

    private function accommodation(PriceBreakdown $price, TripDraft $draft, Itinerary $itinerary): void
    {
        $rooms = (int) ceil(max(1, $draft->adults) / 2);
        $hotelIds = array_column($draft->hotels, 'hotel_id');
        $rateIds = array_filter(array_column($draft->hotels, 'room_rate_id'));
        $hotels = Hotel::whereKey($hotelIds)->get()->keyBy('id');
        $rates = RoomRate::whereKey($rateIds)->get()->keyBy('id');

        foreach ($itinerary->overnights() as $dayNumber => $overnight) {
            $choice = $draft->hotels[$dayNumber] ?? null;
            $hotel = $choice ? $hotels->get($choice['hotel_id']) : null;
            $rate = $choice && $choice['room_rate_id'] ? $rates->get($choice['room_rate_id']) : null;

            if (! $hotel || ! $rate) {
                $price->add(PriceCategory::Accommodation, "Night {$dayNumber}: {$overnight['town']} — hotel to be confirmed", 1, 0);
                $price->notes[] = "Night {$dayNumber} in {$overnight['town']} has no priced hotel yet; your agent will add it.";

                continue;
            }

            $price->add(
                PriceCategory::Accommodation,
                "Night {$dayNumber}: {$hotel->name} ({$rate->room_type}, {$rate->meal_plan->value})",
                $rooms,
                Money::cents($rate->price_per_night),
            );

            $extraBeds = max(0, $draft->adults + $draft->children() - $rooms * max(1, $rate->max_occupancy));
            if ($extraBeds > 0 && Money::cents($rate->extra_bed_price) > 0) {
                $price->add(PriceCategory::Accommodation, "Night {$dayNumber}: extra bed", $extraBeds, Money::cents($rate->extra_bed_price));
            }
        }
    }

    private function transport(PriceBreakdown $price, TripDraft $draft, int $days, float $km): void
    {
        $vehicle = $draft->vehicleId ? Vehicle::find($draft->vehicleId) : null;

        if (! $vehicle) {
            return;
        }

        if ($draft->isSelfDrive() && $vehicle->offersSelfDrive()) {
            $this->selfDrive($price, $vehicle, $days, $km);
        } else {
            $price->add(PriceCategory::Transport, "{$vehicle->type} with driver, {$days} days", $days, Money::cents($vehicle->day_rate));

            if ($km > 0 && Money::cents($vehicle->km_rate) > 0) {
                $price->add(PriceCategory::Transport, 'Mileage, about '.number_format($km).' km', round($km, 1), Money::cents($vehicle->km_rate));
            }
        }

        if ($draft->childSeats() > 0) {
            $price->notes[] = $draft->childSeats().' child seat(s) included for children under 4.';
        }

        // Optional refreshment pack in the vehicle (water, soft drinks, snacks), per person per day.
        $rate = Money::cents(Setting::get('refreshments_per_person_day', '0'));
        $people = $draft->adults + $draft->children();
        if ($draft->refreshments && $rate > 0 && $people > 0) {
            $price->add(PriceCategory::Transport, "Refreshment pack (water, soft drinks, snacks), {$people} × {$days} days", $people * $days, $rate);
        }
    }

    /**
     * Self-drive: the self-drive day rate includes a daily km allowance; kilometres over it are
     * charged at the vehicle's km rate. The deposit is refundable, so it is a note, not a line.
     */
    private function selfDrive(PriceBreakdown $price, Vehicle $vehicle, int $days, float $km): void
    {
        $price->add(PriceCategory::Transport, "{$vehicle->type}, self-drive, {$days} days", $days, Money::cents($vehicle->self_drive_day_rate));

        $included = (int) $vehicle->self_drive_km_per_day * $days;
        $extra = $included > 0 ? max(0, $km - $included) : 0;
        if ($extra > 0 && Money::cents($vehicle->km_rate) > 0) {
            $price->add(PriceCategory::Transport, 'Extra mileage over '.number_format($included).' km included, about '.number_format($extra).' km', round($extra, 1), Money::cents($vehicle->km_rate));
        }

        $price->notes[] = 'Self-drive: fuel is not included. Bring your Sri Lankan driving licence and NIC to the pick-up'
            .(Money::cents($vehicle->self_drive_deposit) > 0 ? ' and pay a refundable deposit of '.Money::show((string) $vehicle->self_drive_deposit).'.' : '.');
    }

    /**
     * Sri Lankan residents get a discount on hotels, transport and guide (Settings → Pricing).
     */
    private function localDiscount(PriceBreakdown $price, TripDraft $draft): void
    {
        $percent = (string) Setting::get('local_discount_percent', '0');
        if (! $draft->isLocal() || (float) $percent <= 0) {
            return;
        }

        $base = $price->categoryTotal(PriceCategory::Accommodation) + $price->categoryTotal(PriceCategory::Transport) + $price->categoryTotal(PriceCategory::Guide);
        $discount = Money::percent($base, $percent);
        if ($discount > 0) {
            $price->add(PriceCategory::Discount, 'Sri Lankan resident discount ('.rtrim(rtrim($percent, '0'), '.').'% on hotels, transport and guide)', 1, -$discount);
        }
    }

    private function guide(PriceBreakdown $price, TripDraft $draft, int $days): void
    {
        $type = GuideType::tryFrom($draft->guideType) ?? GuideType::Chauffeur;

        // Chauffeur-guides add a daily guiding allowance on top of the vehicle rate;
        // national and site guides are priced at their own day rate.
        $guide = $draft->guideId ? Guide::find($draft->guideId) : $this->suggestions->guideFor($type, $draft->guideLanguage);

        if (! $guide || $type === GuideType::None) {
            return;
        }

        $price->add(PriceCategory::Guide, "{$guide->name}, {$days} days", $days, Money::cents($guide->day_rate));
    }

    private function meals(PriceBreakdown $price, TripDraft $draft, Itinerary $itinerary, int $days): void
    {
        $plan = MealPlan::tryFrom((string) $draft->mealPlan) ?? $this->suggestions->defaultMealPlan($draft->tierEnum());
        $wanted = self::MEALS[$plan->value];
        $mealPrices = [
            'breakfast' => Money::cents(Setting::get('meal_breakfast', '0')),
            'lunch' => Money::cents(Setting::get('meal_lunch', '0')),
            'dinner' => Money::cents(Setting::get('meal_dinner', '0')),
        ];
        // Adults pay full price, children half, infants eat free.
        $mealPax = $draft->adults + $draft->children() * 0.5;

        $rates = RoomRate::whereKey(array_filter(array_column($draft->hotels, 'room_rate_id')))->get()->keyBy('id');
        $missing = ['breakfast' => 0, 'lunch' => 0, 'dinner' => 0];

        foreach ($itinerary->overnights() as $dayNumber => $overnight) {
            $rate = $rates->get($draft->hotels[$dayNumber]['room_rate_id'] ?? 0);
            $included = self::MEALS[$rate?->meal_plan->value ?? 'RO'];

            // Dinner the same evening and breakfast next morning come from the hotel.
            foreach (['breakfast', 'dinner'] as $meal) {
                if (in_array($meal, $wanted, true) && ! in_array($meal, $included, true)) {
                    $missing[$meal]++;
                }
            }
        }

        // Lunch is on the road on every day of a full-board plan.
        if (in_array('lunch', $wanted, true)) {
            $missing['lunch'] = $days;
        }

        foreach ($missing as $meal => $count) {
            if ($count > 0 && $mealPrices[$meal] > 0) {
                $price->add(PriceCategory::Meals, ucfirst($meal)." ({$plan->label()}), {$count} × ".rtrim(rtrim(number_format($mealPax, 1), '0'), '.').' people', $count * $mealPax, $mealPrices[$meal]);
            }
        }
    }

    private function tickets(PriceBreakdown $price, TripDraft $draft, Itinerary $itinerary): void
    {
        $freeUnder = (int) Setting::get('ticket_child_free_age', '0');
        $payingChildren = count(array_filter($draft->childrenAges, fn (int $age) => $age >= $freeUnder));
        $places = Place::whereKey($itinerary->placeIds())->get()->keyBy('id');

        foreach ($itinerary->placeIds() as $id) {
            $place = $places->get($id);
            if (! $place) {
                continue;
            }

            // Sri Lankan residents pay the local entry fee (often much lower, sometimes free).
            $local = $draft->isLocal();
            $adult = Money::cents($local ? $place->fee_local_adult : $place->fee_foreign_adult);
            $child = Money::cents($local ? $place->fee_local_child : $place->fee_foreign_child);
            $who = $local ? 'resident' : 'foreign';

            if ($adult > 0 && $draft->adults > 0) {
                $price->add(PriceCategory::Tickets, "{$place->name}: {$who} adult ticket", $draft->adults, $adult);
            }
            if ($child > 0 && $payingChildren > 0) {
                $price->add(PriceCategory::Tickets, "{$place->name}: {$who} child ticket", $payingChildren, $child);
            }
        }
    }
}
