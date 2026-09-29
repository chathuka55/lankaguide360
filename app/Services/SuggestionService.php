<?php

namespace App\Services;

use App\Enums\GuideType;
use App\Enums\MealPlan;
use App\Enums\Tier;
use App\Models\Guide;
use App\Models\Hotel;
use App\Models\RoomRate;
use App\Models\Vehicle;
use App\Support\TripDraft;
use Illuminate\Support\Collection;

/**
 * Hotel, vehicle, guide and meal-plan suggestions (SRS 5.2, 5.5, FR-10 to FR-13).
 */
class SuggestionService
{
    /**
     * Default meal plan per tier (SRS 5.2).
     */
    public function defaultMealPlan(Tier $tier): MealPlan
    {
        return match ($tier) {
            Tier::Budget => MealPlan::BedAndBreakfast,
            Tier::Premium => MealPlan::HalfBoard,
            Tier::Luxury => MealPlan::FullBoard,
        };
    }

    /**
     * Up to $limit published hotels for one night in $town (then nearby), matching the tier.
     * Kid-friendly hotels come first when the group has children.
     *
     * @param  array{pool?: bool, beach_front?: bool, kid_friendly?: bool}  $filters
     * @return Collection<int, array{hotel: Hotel, rate: ?RoomRate}>
     */
    public function hotelsFor(string $town, float $lat, float $lng, Tier $tier, ?string $date, MealPlan $mealPlan, array $filters = [], int $limit = 3): Collection
    {
        $query = fn () => Hotel::published()
            ->tier($tier)
            ->with(['roomRates', 'cover' => fn ($q) => $q->where('status', 'published')])
            ->when($filters['pool'] ?? false, fn ($q) => $q->whereJsonContains('amenities', 'pool'))
            ->when($filters['beach_front'] ?? false, fn ($q) => $q->whereJsonContains('amenities', 'beach_front'))
            ->when($filters['kid_friendly'] ?? false, fn ($q) => $q->orderByDesc('kid_friendly'));

        $inTown = $query()->where('town', $town)->orderByDesc('star_rating')->orderBy('name')->limit($limit * 3)->get();
        $nearby = $inTown->count() >= $limit
            ? collect()
            : $query()->nearTo($lat, $lng, 25)->whereNotIn('hotels.id', $inTown->pluck('id'))->limit($limit * 3)->get();

        return $inTown->concat($nearby)
            // Hotels with a price first: the builder can only price those.
            ->sortBy(fn (Hotel $hotel) => [$this->rateFor($hotel, $date, $mealPlan) ? 0 : 1, ($filters['kid_friendly'] ?? false) && $hotel->kid_friendly ? 0 : 1])
            ->take($limit)
            ->values()
            ->map(fn (Hotel $hotel) => ['hotel' => $hotel, 'rate' => $this->rateFor($hotel, $date, $mealPlan)]);
    }

    /**
     * The cheapest rate valid on $date, preferring the requested meal plan.
     */
    public function rateFor(Hotel $hotel, ?string $date, MealPlan $mealPlan): ?RoomRate
    {
        $valid = $this->ratesFor($hotel, $date);

        return $valid->where('meal_plan', $mealPlan)->first() ?? $valid->first();
    }

    /**
     * Room rates valid on $date, cheapest first (builder step 6 room choice).
     *
     * @return Collection<int, RoomRate>
     */
    public function ratesFor(Hotel $hotel, ?string $date): Collection
    {
        return $hotel->roomRates
            ->filter(fn (RoomRate $rate) => $date === null
                || (($rate->season_from === null || $rate->season_from->toDateString() <= $date)
                    && ($rate->season_to === null || $rate->season_to->toDateString() >= $date)))
            ->sortBy(fn (RoomRate $r) => (float) $r->price_per_night)
            ->values();
    }

    /**
     * Vehicle for the group (SRS 5.5): seats for everyone including children and infants;
     * more than 2 bags per person moves the group up one vehicle size.
     */
    public function vehicleFor(int $travellers, ?int $bags, Tier $tier): ?Vehicle
    {
        $fleet = Vehicle::tier($tier)->orderBy('max_pax')->get();
        $index = $fleet->search(fn (Vehicle $v) => $v->min_pax <= $travellers && $v->max_pax >= $travellers);

        if ($index === false) {
            $index = $fleet->search(fn (Vehicle $v) => $v->max_pax >= $travellers);
        }

        if ($index === false) {
            return $fleet->last();
        }

        $vehicle = $fleet[$index];

        $tooMuchLuggage = $bags !== null && ($bags > 2 * $travellers || ($vehicle->luggage_capacity !== null && $bags > $vehicle->luggage_capacity));
        if ($tooMuchLuggage && isset($fleet[$index + 1])) {
            return $fleet[$index + 1];
        }

        return $vehicle;
    }

    /**
     * Vehicles the traveller may switch to: any tier, but only with enough seats.
     *
     * @return Collection<int, Vehicle>
     */
    public function vehicleOptions(int $travellers): Collection
    {
        return Vehicle::where('max_pax', '>=', $travellers)
            ->orderByRaw("FIELD(tier, 'budget', 'premium', 'luxury')")
            ->orderBy('max_pax')
            ->get();
    }

    /**
     * Guide rule for the group size and tier (SRS 5.2, 5.5).
     *
     * @return array{type: GuideType, required: bool, options: array<int, GuideType>, note: string}
     */
    public function guideRule(int $travellers, Tier $tier): array
    {
        return match (true) {
            $travellers >= 21 => ['type' => GuideType::National, 'required' => true, 'options' => [GuideType::National],
                'note' => 'Large groups travel with a driver, an SLTDA national guide and an assistant.'],
            $travellers >= 10 => ['type' => GuideType::National, 'required' => true, 'options' => [GuideType::National],
                'note' => 'Groups of 10 or more have a separate driver and an SLTDA national guide.'],
            $tier === Tier::Luxury => ['type' => GuideType::National, 'required' => false, 'options' => [GuideType::National, GuideType::Chauffeur, GuideType::Site],
                'note' => 'Luxury trips include an SLTDA national guide with your driver, in your language.'],
            $travellers >= 7 => ['type' => GuideType::Chauffeur, 'required' => false, 'options' => [GuideType::Chauffeur, GuideType::Site, GuideType::National],
                'note' => 'A chauffeur-guide drives you; site guides at major sites are optional.'],
            $tier === Tier::Premium => ['type' => GuideType::Chauffeur, 'required' => false, 'options' => [GuideType::Chauffeur, GuideType::Site, GuideType::National],
                'note' => 'A chauffeur-guide, plus site guides at the major sites.'],
            default => ['type' => GuideType::Chauffeur, 'required' => false, 'options' => [GuideType::Chauffeur, GuideType::Site, GuideType::National, GuideType::None],
                'note' => 'Your driver is a chauffeur-guide who speaks English.'],
        };
    }

    /**
     * An available guide of the type who speaks the language (English as a fallback).
     */
    public function guideFor(GuideType $type, string $language): ?Guide
    {
        if ($type === GuideType::None) {
            return null;
        }

        $base = fn () => Guide::available()->where('type', $type->value)->orderBy('day_rate');

        return $base()->speaks($language)->first() ?? $base()->speaks('English')->first() ?? $base()->first();
    }

    /**
     * Languages offered by available guides.
     *
     * @return array<int, string>
     */
    public function guideLanguages(): array
    {
        return Guide::available()->pluck('languages')->flatten()->unique()->sort()->values()->all() ?: ['English'];
    }

    /**
     * Defaults applied when the traveller first reaches steps 6 and 7.
     */
    public function applyDefaults(TripDraft $draft): void
    {
        $tier = $draft->tierEnum();
        $draft->mealPlan ??= $this->defaultMealPlan($tier)->value;
        $draft->vehicleId ??= $this->vehicleFor($draft->travellers(), $draft->luggage, $tier)?->id;

        if ($draft->completedStep < 7) {
            $draft->guideType = $this->guideRule($draft->travellers(), $tier)['type']->value;
        }
    }
}
