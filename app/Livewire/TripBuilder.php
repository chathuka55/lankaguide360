<?php

namespace App\Livewire;

use App\Enums\GuideType;
use App\Enums\MealPlan;
use App\Enums\PriceCategory;
use App\Enums\Tier;
use App\Models\Category;
use App\Models\Cuisine;
use App\Models\District;
use App\Models\Hotel;
use App\Models\Place;
use App\Models\Setting;
use App\Models\Vehicle;
use App\Services\Itinerary\Itinerary;
use App\Services\ItineraryService;
use App\Services\PricingService;
use App\Services\SuggestionService;
use App\Support\Money;
use App\Support\TripDraft;
use App\Support\TripMapData;
use App\Support\TripPoints;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * The 8-step Trip Builder wizard (SRS 5.1, FR-03 to FR-17, FR-27). All choices live in the
 * session as a TripDraft, so a refresh (or leaving and coming back) keeps everything.
 * Business rules stay in ItineraryService, SuggestionService and PricingService.
 */
class TripBuilder extends Component
{
    public const STEPS = [
        1 => 'Travel style',
        2 => 'Interests',
        3 => 'Districts',
        4 => 'Places',
        5 => 'Dates & travellers',
        6 => 'Stays & meals',
        7 => 'Transport & guide',
        8 => 'Review & submit',
    ];

    /** Changing any of these makes the generated itinerary (and hotel choices) stale. */
    private const ITINERARY_FIELDS = ['tier', 'placeIds', 'startDate', 'days', 'arrivalPoint', 'departurePoint'];

    #[Url(except: 1)]
    public int $step = 1;

    public int $completedStep = 0;

    public string $tier = '';

    /** @var array<int, int> */
    public array $categoryIds = [];

    /** @var array<int, int> */
    public array $districtIds = [];

    /** @var array<int, int> */
    public array $placeIds = [];

    public ?int $activeDistrictId = null;

    public ?int $placeCategoryFilter = null;

    public ?string $startDate = null;

    public ?int $days = null;

    public int $adults = 2;

    /** @var array<int, int|string> */
    public array $childrenAges = [];

    public int $infants = 0;

    public string $arrivalPoint = 'BIA Katunayake';

    public string $departurePoint = 'BIA Katunayake';

    public ?int $luggage = null;

    public string $mealPlan = '';

    /** @var array<int, int> */
    public array $cuisineIds = [];

    public ?string $dietaryNotes = null;

    /** @var array{kid_friendly: bool, pool: bool, beach_front: bool} */
    public array $hotelFilters = ['kid_friendly' => false, 'pool' => false, 'beach_front' => false];

    public ?int $vehicleId = null;

    public string $guideType = 'chauffeur';

    public string $guideLanguage = 'English';

    /** "local" (Sri Lankan resident prices) or "foreign". */
    public string $travellerType = 'foreign';

    public bool $refreshments = false;

    /** Self-drive hire: Sri Lankan residents with a valid Sri Lankan licence only. */
    public bool $selfDrive = false;

    public ?string $driverName = null;

    public ?string $driverLicenceNo = null;

    public ?string $driverLicenceExpiry = null;

    public bool $licenceConfirmed = false;

    public function mount(): void
    {
        $draft = TripDraft::fromSession(session()->driver());

        // Deep links: /plan?category=beach-coastal, /plan?place=galle-fort
        if ($category = Category::where('slug', (string) request()->query('category'))->first()) {
            $draft->categoryIds = array_values(array_unique([...$draft->categoryIds, $category->id]));
        }
        if ($place = Place::published()->where('slug', (string) request()->query('place'))->first()) {
            $draft->placeIds = array_values(array_unique([...$draft->placeIds, $place->id]));
        }

        $this->absorbPickedPlaces($draft);
        $this->fillFrom($draft);
        $this->hotelFilters['kid_friendly'] = $draft->children() > 0;
        $this->step = max(1, min($this->step, $this->completedStep + 1, 8));
        $this->persist();
    }

    // ---------------------------------------------------------------- navigation

    public function next(): void
    {
        $this->validateStep($this->step);

        if ($this->step === 5 || ($this->step >= 5 && ! $this->draft()->hasItinerary())) {
            $this->generate();
        }

        $this->completedStep = max($this->completedStep, $this->step);
        $this->step = min(8, $this->step + 1);
        $this->persist();
        $this->enterStep();
    }

    public function back(): void
    {
        $this->step = max(1, $this->step - 1);
        $this->persist();
    }

    public function goTo(int $step): void
    {
        if ($step < 1 || $step > 8 || $step > $this->completedStep + 1) {
            return;
        }

        $this->step = $step;
        $this->persist();
        $this->enterStep();
    }

    /** Steps 6–8 need an itinerary; regenerate it if an earlier change made it stale. */
    private function enterStep(): void
    {
        if ($this->step >= 6 && ! $this->draft()->hasItinerary() && $this->placeIds && $this->startDate) {
            $this->generate();
        }
    }

    // ---------------------------------------------------------------- step 1–4 actions

    public function selectTier(string $tier): void
    {
        $tier = Tier::tryFrom($tier);
        if (! $tier || $tier->value === $this->tier) {
            return;
        }

        $suggestions = app(SuggestionService::class);
        $this->tier = $tier->value;
        $this->mealPlan = $suggestions->defaultMealPlan($tier)->value;
        $this->vehicleId = $suggestions->vehicleFor($this->travellerCount(), $this->luggage, $tier, $this->isSelfDrive())?->id;
        $this->guideType = $suggestions->guideRule($this->travellerCount(), $tier, $this->isSelfDrive())['type']->value;
        $this->persist(invalidate: true);
    }

    public function toggleCategory(int $id): void
    {
        $this->categoryIds = $this->toggle($this->categoryIds, $id);
        $this->pruneDistricts();
        $this->persist();
    }

    public function toggleDistrict(int $id): void
    {
        if (! in_array($id, $this->districtIds, true) && ! $this->allowedDistrictIds()->contains($id)) {
            return;
        }

        $this->districtIds = $this->toggle($this->districtIds, $id);
        $this->prunePlaces();
        $this->persist();
        $this->dispatch('districts-changed', ids: $this->districtIds);
    }

    public function togglePlace(int $id): void
    {
        $place = Place::published()->find($id);
        if (! $place) {
            return;
        }

        $this->placeIds = $this->toggle($this->placeIds, $id);
        if (in_array($id, $this->placeIds, true) && ! in_array($place->district_id, $this->districtIds, true)) {
            $this->districtIds[] = (int) $place->district_id;
        }
        $this->persist(invalidate: true);
    }

    /**
     * Places step: move on to the next chosen district (feedback: don't forget a district).
     */
    public function nextDistrict(): void
    {
        $ids = District::whereKey($this->districtIds)->orderBy('name')->pluck('id')->all();
        $current = array_search($this->activeDistrictId ?? ($ids[0] ?? null), $ids, true);
        $this->activeDistrictId = $ids[$current === false ? 0 : min($current + 1, count($ids) - 1)] ?? null;
        $this->placeCategoryFilter = null;
    }

    /**
     * Places step: drop a chosen district (and its places) instead of going back to step 3.
     */
    public function removeDistrict(int $id): void
    {
        $this->districtIds = array_values(array_diff(array_map('intval', $this->districtIds), [$id]));
        $this->prunePlaces();
        $this->persist(invalidate: true);
        $this->activeDistrictId = null;
        $this->resetErrorBag('placeIds');
    }

    public function showDistrict(int $id): void
    {
        $this->activeDistrictId = $id;
    }

    public function filterPlaces(?int $categoryId = null): void
    {
        $this->placeCategoryFilter = $categoryId;
    }

    // ---------------------------------------------------------------- step 5 actions

    public function addChild(): void
    {
        if (count($this->childrenAges) < 10) {
            $this->childrenAges[] = 8;
            $this->travellersChanged();
        }
    }

    public function removeChild(int $index): void
    {
        unset($this->childrenAges[$index]);
        $this->childrenAges = array_values($this->childrenAges);
        $this->travellersChanged();
    }

    public function useRecommendedDays(): void
    {
        $this->days = $this->recommendedDays() ?: $this->days;
        $this->persist(invalidate: true);
    }

    // ---------------------------------------------------------------- step 6 actions

    public function chooseHotel(int $day, int $hotelId): void
    {
        $draft = $this->draft();
        $hotel = Hotel::published()->with('roomRates')->find($hotelId);
        $date = collect($draft->itinerary['days'] ?? [])->firstWhere('number', $day)['date'] ?? null;

        if (! $hotel || ! isset(Itinerary::fromArray($draft->itinerary)->overnights()[$day])) {
            return;
        }

        $rate = app(SuggestionService::class)->rateFor($hotel, $date, MealPlan::from($this->mealPlan ?: 'BB'));
        $draft->hotels[$day] = ['hotel_id' => $hotel->id, 'room_rate_id' => $rate?->id];
        $this->save($draft);
    }

    public function chooseRate(int $day, int $rateId): void
    {
        $draft = $this->draft();
        $choice = $draft->hotels[$day] ?? null;

        if ($choice && Hotel::find($choice['hotel_id'])?->roomRates()->whereKey($rateId)->exists()) {
            $draft->hotels[$day]['room_rate_id'] = $rateId;
            $this->save($draft);
        }
    }

    public function toggleHotelFilter(string $key): void
    {
        if (array_key_exists($key, $this->hotelFilters)) {
            $this->hotelFilters[$key] = ! $this->hotelFilters[$key];
        }
    }

    public function toggleCuisine(int $id): void
    {
        $this->cuisineIds = $this->toggle($this->cuisineIds, $id);
        $this->persist();
    }

    // ---------------------------------------------------------------- step 7 actions

    public function selectVehicle(int $id): void
    {
        $vehicle = Vehicle::find($id);

        if ($vehicle && $vehicle->max_pax >= $this->travellerCount() && (! $this->isSelfDrive() || $vehicle->offersSelfDrive())) {
            $this->vehicleId = $vehicle->id;
            $this->persist();
        }
    }

    // ---------------------------------------------------------------- step 8 actions

    /**
     * Drag and drop on the review timeline (FR-17): new place order per day, then re-time.
     *
     * @param  array<int, array<int, int|string>>  $days  place ids per day, in order
     */
    public function reorder(array $days): void
    {
        $draft = $this->draft();
        $current = Itinerary::fromArray($draft->itinerary);
        $known = collect($current->placeIds());

        $days = array_values(array_map(
            fn ($ids) => array_values(array_filter(array_map('intval', (array) $ids), fn (int $id) => $known->contains($id))),
            array_slice($days, 0, max(1, $current->dayCount())),
        ));

        $itinerary = app(ItineraryService::class)->retime($draft, $days);
        $draft->itinerary = [...$itinerary->toArray(), 'dropped' => $current->dropped];
        $this->keepHotels($draft);
        $this->save($draft);

        $this->dispatch('itinerary-updated', days: TripMapData::fromItinerary($draft->itinerary));
    }

    public function regenerate(): void
    {
        $this->generate();
        $this->dispatch('itinerary-updated', days: TripMapData::fromItinerary($this->draft()->itinerary ?? []));
    }

    // ---------------------------------------------------------------- property hooks

    public function updated(string $property): void
    {
        $name = Str::before($property, '.');

        match (true) {
            in_array($name, ['adults', 'infants', 'childrenAges', 'luggage'], true) => $this->travellersChanged(),
            $name === 'mealPlan' => $this->mealPlanChanged(),
            in_array($name, ['selfDrive', 'travellerType'], true) => $this->selfDriveChanged(),
            default => $this->persist(invalidate: in_array($name, self::ITINERARY_FIELDS, true)),
        };
    }

    private function travellersChanged(): void
    {
        $this->adults = max(1, min(40, (int) $this->adults));
        $this->infants = max(0, min(10, (int) $this->infants));
        $this->childrenAges = array_values(array_map(fn ($age) => max(2, min(17, (int) $age)), $this->childrenAges));
        $this->hotelFilters['kid_friendly'] = $this->childrenAges !== [];

        $suggestions = app(SuggestionService::class);
        $tier = Tier::tryFrom($this->tier) ?? Tier::Premium;
        $current = $this->vehicleId ? Vehicle::find($this->vehicleId) : null;
        if (! $current || $current->max_pax < $this->travellerCount() || $this->completedStep < 7) {
            $this->vehicleId = $suggestions->vehicleFor($this->travellerCount(), $this->luggage, $tier, $this->isSelfDrive())?->id;
        }

        $rule = $suggestions->guideRule($this->travellerCount(), $tier, $this->isSelfDrive());
        if ($this->completedStep < 7 || ! in_array(GuideType::tryFrom($this->guideType), $rule['options'], true)) {
            $this->guideType = $rule['type']->value;
        }

        $this->persist();
    }

    /**
     * Self-drive switched on or off (or the traveller is no longer a resident): pick a vehicle
     * that can be hired that way and a guide option that works without a driver.
     */
    private function selfDriveChanged(): void
    {
        if ($this->travellerType !== 'local') {
            $this->selfDrive = false;
        }

        $suggestions = app(SuggestionService::class);
        $tier = Tier::tryFrom($this->tier) ?? Tier::Premium;
        $current = $this->vehicleId ? Vehicle::find($this->vehicleId) : null;
        $fits = $current && $current->max_pax >= $this->travellerCount() && (! $this->isSelfDrive() || $current->offersSelfDrive());
        if (! $fits) {
            $this->vehicleId = $suggestions->vehicleFor($this->travellerCount(), $this->luggage, $tier, $this->isSelfDrive())?->id;
        }

        $rule = $suggestions->guideRule($this->travellerCount(), $tier, $this->isSelfDrive());
        if (! in_array(GuideType::tryFrom($this->guideType), $rule['options'], true)) {
            $this->guideType = $rule['type']->value;
        }

        $this->persist();
    }

    /**
     * Step 7 "How do you want to travel?": "driver" (driver only), "guide" (a driver who is also
     * the guide, or a national guide riding along) or "self" (self-drive, residents only).
     */
    public function chooseDriveMode(string $mode): void
    {
        if ($mode === 'self') {
            if ($this->travellerType !== 'local') {
                return;
            }
            $this->selfDrive = true;
            $this->guideType = GuideType::None->value;
            $this->selfDriveChanged();

            return;
        }

        if (! in_array($mode, ['driver', 'guide'], true)) {
            return;
        }

        $rule = app(SuggestionService::class)->guideRule($this->travellerCount(), Tier::tryFrom($this->tier) ?? Tier::Premium);
        $wanted = $mode === 'guide' ? [GuideType::Chauffeur, GuideType::National] : [GuideType::None, GuideType::Site];
        $this->selfDrive = false;
        $this->guideType = (collect($wanted)->first(fn (GuideType $type) => in_array($type, $rule['options'], true)) ?? $rule['type'])->value;
        $this->selfDriveChanged();
    }

    private function driveMode(): string
    {
        return match (true) {
            $this->isSelfDrive() => 'self',
            in_array($this->guideType, [GuideType::Chauffeur->value, GuideType::National->value], true) => 'guide',
            default => 'driver',
        };
    }

    private function isSelfDrive(): bool
    {
        return $this->selfDrive && $this->travellerType === 'local';
    }

    private function mealPlanChanged(): void
    {
        if (! MealPlan::tryFrom($this->mealPlan)) {
            return;
        }

        $this->persist();

        // Re-price every night with the new meal plan (FR-11).
        $draft = $this->draft();
        $suggestions = app(SuggestionService::class);
        $dates = collect($draft->itinerary['days'] ?? [])->pluck('date', 'number');
        $hotels = Hotel::with('roomRates')->whereKey(array_column($draft->hotels, 'hotel_id'))->get()->keyBy('id');
        foreach ($draft->hotels as $day => $choice) {
            if ($hotel = $hotels->get($choice['hotel_id'])) {
                $draft->hotels[$day]['room_rate_id'] = $suggestions->rateFor($hotel, $dates[$day] ?? null, MealPlan::from($this->mealPlan))?->id;
            }
        }
        $this->save($draft);
    }

    // ---------------------------------------------------------------- engine

    private function generate(): void
    {
        $draft = $this->draft();
        $itinerary = app(ItineraryService::class)->generate($draft);
        $draft->itinerary = $itinerary->toArray();
        app(SuggestionService::class)->applyDefaults($draft);
        $this->keepHotels($draft);
        $this->save($draft);
        $this->fillFrom($draft);
    }

    /**
     * Keep the traveller's hotel for nights whose town did not change; suggest the best-priced
     * matching hotel for the others (FR-10).
     */
    private function keepHotels(TripDraft $draft): void
    {
        $itinerary = Itinerary::fromArray($draft->itinerary);
        $suggestions = app(SuggestionService::class);
        $mealPlan = MealPlan::tryFrom((string) $draft->mealPlan) ?? $suggestions->defaultMealPlan($draft->tierEnum());
        $existing = Hotel::whereKey(array_column($draft->hotels, 'hotel_id'))->pluck('town', 'id');
        $dates = collect($itinerary->days)->pluck('date', 'number');
        $hotels = [];

        foreach ($itinerary->overnights() as $day => $night) {
            $choice = $draft->hotels[$day] ?? null;
            if ($choice && ($existing[$choice['hotel_id']] ?? null) === $night['town']) {
                $hotels[$day] = $choice;

                continue;
            }

            $best = $suggestions->hotelsFor($night['town'], (float) $night['lat'], (float) $night['lng'], $draft->tierEnum(), $dates[$day] ?? null, $mealPlan, ['kid_friendly' => $draft->children() > 0], 1)->first();
            if ($best) {
                $hotels[$day] = ['hotel_id' => $best['hotel']->id, 'room_rate_id' => $best['rate']?->id];
            }
        }

        $draft->hotels = $hotels;
    }

    // ---------------------------------------------------------------- validation

    private function validateStep(int $step): void
    {
        if ($step >= 8) {
            return;
        }

        $this->validate(...match ($step) {
            1 => [['tier' => ['required', Rule::enum(Tier::class)]], ['tier.required' => 'Choose a travel style to continue.']],
            2 => [['categoryIds' => ['required', 'array', 'min:1'], 'categoryIds.*' => ['integer', 'exists:categories,id']], ['categoryIds.required' => 'Pick at least one interest.', 'categoryIds.min' => 'Pick at least one interest.']],
            3 => [['districtIds' => ['required', 'array', 'min:1'], 'districtIds.*' => ['integer', 'exists:districts,id']], ['districtIds.required' => 'Choose at least one district on the map or in the list.', 'districtIds.min' => 'Choose at least one district on the map or in the list.']],
            4 => [['placeIds' => ['required', 'array', 'min:1', 'max:60'], 'placeIds.*' => ['integer', 'exists:places,id']], ['placeIds.required' => 'Add at least one place to your trip.', 'placeIds.min' => 'Add at least one place to your trip.']],
            5 => [[
                'startDate' => ['required', 'date', 'after_or_equal:today', 'before:+2 years'],
                'days' => ['required', 'integer', 'between:1,21'],
                'adults' => ['required', 'integer', 'between:1,40'],
                'childrenAges' => ['array', 'max:10'],
                'childrenAges.*' => ['integer', 'between:2,17'],
                'infants' => ['integer', 'between:0,10'],
                'arrivalPoint' => ['required', Rule::in(TripPoints::names())],
                'departurePoint' => ['required', Rule::in(TripPoints::names())],
                'luggage' => ['nullable', 'integer', 'between:0,80'],
                'travellerType' => ['required', Rule::in(['local', 'foreign'])],
            ], ['startDate.after_or_equal' => 'The start date cannot be in the past.', 'childrenAges.*.between' => 'Children are 2–17 years old; add younger ones as infants.'], ['startDate' => 'start date', 'days' => 'number of days']],
            6 => [['mealPlan' => ['required', Rule::enum(MealPlan::class)], 'cuisineIds.*' => ['integer', 'exists:cuisines,id'], 'dietaryNotes' => ['nullable', 'string', 'max:500']]],
            7 => [[
                'vehicleId' => ['required', 'integer', Rule::exists('vehicles', 'id')->where(fn ($q) => $q->where('max_pax', '>=', $this->travellerCount())
                    ->when($this->isSelfDrive(), fn ($q) => $q->where('self_drive', true)->where('self_drive_day_rate', '>', 0)))],
                'guideType' => ['required', Rule::enum(GuideType::class), ...($this->isSelfDrive() ? [Rule::notIn([GuideType::Chauffeur->value])] : [])],
                'guideLanguage' => ['required', 'string', 'max:30'],
                ...($this->isSelfDrive() ? self::licenceRules($this->draft()->end()?->toDateString()) : []),
            ], [
                'vehicleId.required' => 'Choose a vehicle.',
                'vehicleId.exists' => $this->isSelfDrive() ? 'Choose a vehicle that can be hired as self-drive.' : 'That vehicle has too few seats for your group.',
                'guideType.not_in' => 'A chauffeur-guide needs a driver. Choose another guide option for self-drive.',
                ...self::licenceMessages(),
            ], ['driverName' => 'driver name', 'driverLicenceNo' => 'licence number', 'driverLicenceExpiry' => 'licence expiry date']],
        });

        if ($step === 4) {
            $this->validatePlacesAreRoutable();
        }
    }

    /**
     * Self-drive licence checks (also used by the vehicle hire form). The website can only check
     * the details given; the agent checks the licence itself at pick-up.
     *
     * @return array<string, array<int, mixed>>
     */
    public static function licenceRules(?string $lastDay): array
    {
        return [
            'driverName' => ['required', 'string', 'max:120'],
            'driverLicenceNo' => ['required', 'string', 'max:20', 'regex:/^[A-Za-z]{0,2}\s?\d{5,10}$/'],
            'driverLicenceExpiry' => ['required', 'date', 'after_or_equal:'.($lastDay ?? 'today')],
            'licenceConfirmed' => ['accepted'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function licenceMessages(): array
    {
        return [
            'driverLicenceNo.regex' => 'Enter the number as printed on your Sri Lankan driving licence, e.g. B1234567.',
            'driverLicenceExpiry.after_or_equal' => 'Your licence must be valid until the last day of the trip.',
            'licenceConfirmed.accepted' => 'Please confirm you hold a valid Sri Lankan driving licence for this vehicle.',
        ];
    }

    private function validatePlacesAreRoutable(): void
    {
        $routable = Place::published()->whereKey($this->placeIds)->whereNotNull('lat')->count();

        if ($routable === 0) {
            throw ValidationException::withMessages(['placeIds' => 'None of these places has a map position yet. Please choose others.']);
        }

        // Every chosen district needs at least one place (or the traveller removes it).
        $picked = Place::whereKey($this->placeIds)->pluck('district_id')->map(fn ($id) => (int) $id)->unique();
        $empty = District::whereKey($this->districtIds)->whereNotIn('id', $picked)->orderBy('name')->get(['id', 'name']);
        if ($empty->isNotEmpty()) {
            $this->activeDistrictId = $empty->first()->id;
            $names = $empty->pluck('name')->join(', ', ' and ');
            throw ValidationException::withMessages(['placeIds' => "You chose {$names} but haven't added a place there yet. Add at least one place, or remove the district."]);
        }
    }

    // ---------------------------------------------------------------- state helpers

    private function draft(): TripDraft
    {
        return TripDraft::fromSession(session()->driver());
    }

    private function save(TripDraft $draft): void
    {
        $draft->saveTo(session()->driver());
    }

    /**
     * Write the form fields into the session draft.
     */
    private function persist(bool $invalidate = false): void
    {
        $draft = $this->draft();
        $draft->tier = $this->tier ?: null;
        $draft->categoryIds = array_values(array_map('intval', $this->categoryIds));
        $draft->districtIds = array_values(array_map('intval', $this->districtIds));
        $draft->placeIds = array_values(array_map('intval', $this->placeIds));
        $draft->startDate = $this->startDate ?: null;
        $draft->days = $this->days ? (int) $this->days : null;
        $draft->adults = max(1, (int) $this->adults);
        $draft->childrenAges = array_values(array_map('intval', $this->childrenAges));
        $draft->infants = max(0, (int) $this->infants);
        $draft->arrivalPoint = $this->arrivalPoint;
        $draft->departurePoint = $this->departurePoint;
        $draft->luggage = $this->luggage === null || $this->luggage === '' ? null : (int) $this->luggage;
        $draft->mealPlan = $this->mealPlan ?: null;
        $draft->cuisineIds = array_values(array_map('intval', $this->cuisineIds));
        $draft->dietaryNotes = $this->dietaryNotes ?: null;
        $draft->vehicleId = $this->vehicleId;
        $draft->guideType = $this->guideType;
        $draft->guideLanguage = $this->guideLanguage;
        $draft->travellerType = in_array($this->travellerType, ['local', 'foreign'], true) ? $this->travellerType : 'foreign';
        $draft->refreshments = (bool) $this->refreshments;
        $draft->selfDrive = $this->isSelfDrive();
        $draft->driverName = $this->driverName ? Str::limit(trim($this->driverName), 120, '') : null;
        $draft->driverLicenceNo = $this->driverLicenceNo ? strtoupper((string) preg_replace('/\s+/', '', $this->driverLicenceNo)) : null;
        $draft->driverLicenceExpiry = $this->driverLicenceExpiry ?: null;
        $draft->licenceConfirmed = (bool) $this->licenceConfirmed;
        $draft->completedStep = $this->completedStep;

        if ($invalidate) {
            $draft->itinerary = null;
            $draft->hotels = [];
            // Changing the plan after the review step sends the traveller through it again.
            $this->completedStep = $draft->completedStep = min($this->completedStep, max(4, $this->step - 1));
        }

        $this->save($draft);
    }

    private function fillFrom(TripDraft $draft): void
    {
        $this->tier = (string) $draft->tier;
        $this->categoryIds = $draft->categoryIds;
        $this->districtIds = $draft->districtIds;
        $this->placeIds = $draft->placeIds;
        $this->startDate = $draft->startDate;
        $this->days = $draft->days;
        $this->adults = $draft->adults;
        $this->childrenAges = $draft->childrenAges;
        $this->infants = $draft->infants;
        $this->arrivalPoint = $draft->arrivalPoint;
        $this->departurePoint = $draft->departurePoint;
        $this->luggage = $draft->luggage;
        $this->mealPlan = (string) ($draft->mealPlan ?? ($draft->tier ? app(SuggestionService::class)->defaultMealPlan($draft->tierEnum())->value : ''));
        $this->cuisineIds = $draft->cuisineIds;
        $this->dietaryNotes = $draft->dietaryNotes;
        $this->vehicleId = $draft->vehicleId;
        $this->guideType = $draft->guideType;
        $this->guideLanguage = $draft->guideLanguage;
        // Registered travellers start with the type from their profile.
        $this->travellerType = $draft->travellerType ?? (auth()->user()?->traveller_type === 'local' ? 'local' : 'foreign');
        $this->refreshments = $draft->refreshments;
        $this->selfDrive = $draft->selfDrive;
        $this->driverName = $draft->driverName ?? auth()->user()?->name;
        $this->driverLicenceNo = $draft->driverLicenceNo;
        $this->driverLicenceExpiry = $draft->driverLicenceExpiry;
        $this->licenceConfirmed = $draft->licenceConfirmed;
        $this->completedStep = $draft->completedStep;
    }

    /**
     * Places picked with "Add to my trip" bring their district and categories along.
     */
    private function absorbPickedPlaces(TripDraft $draft): void
    {
        $places = Place::published()->whereKey($draft->placeIds)->with('categories:id')->get();
        $draft->placeIds = $places->pluck('id')->all();
        $draft->districtIds = array_values(array_unique([...$draft->districtIds, ...$places->pluck('district_id')->map(fn ($id) => (int) $id)]));

        if ($draft->categoryIds === []) {
            $draft->categoryIds = $places->flatMap(fn (Place $p) => $p->categories->pluck('id'))->unique()->values()->all();
        }
    }

    private function pruneDistricts(): void
    {
        $allowed = $this->allowedDistrictIds();
        $this->districtIds = array_values(array_filter($this->districtIds, fn (int $id) => $allowed->contains($id)));
        $this->prunePlaces();
    }

    private function prunePlaces(): void
    {
        $keep = Place::whereKey($this->placeIds)->whereIn('district_id', $this->districtIds)->pluck('id');
        $before = count($this->placeIds);
        $this->placeIds = array_values(array_filter($this->placeIds, fn (int $id) => $keep->contains($id)));

        if (count($this->placeIds) !== $before) {
            $this->persist(invalidate: true);
        }
    }

    /**
     * Districts linked to the chosen interests (SRS 4.1), plus districts of places already picked.
     *
     * @return Collection<int, int>
     */
    private function allowedDistrictIds(): Collection
    {
        $linked = District::whereHas('categories', fn ($q) => $q->whereKey($this->categoryIds))->pluck('id');
        $picked = Place::whereKey($this->placeIds)->pluck('district_id');

        return $linked->concat($picked)->map(fn ($id) => (int) $id)->unique()->values();
    }

    private function travellerCount(): int
    {
        return max(1, (int) $this->adults) + count($this->childrenAges) + max(0, (int) $this->infants);
    }

    private function recommendedDays(): int
    {
        $places = Place::published()->whereKey($this->placeIds)->get();

        return app(ItineraryService::class)->recommendedDays($places, Tier::tryFrom($this->tier) ?? Tier::Premium);
    }

    /**
     * @param  array<int, int>  $list
     * @return array<int, int>
     */
    private function toggle(array $list, int $id): array
    {
        $list = array_map('intval', $list);

        return in_array($id, $list, true)
            ? array_values(array_diff($list, [$id]))
            : [...$list, $id];
    }

    // ---------------------------------------------------------------- view data

    public function render(): View
    {
        $draft = $this->draft();
        $tier = Tier::tryFrom($this->tier);
        $itinerary = Itinerary::fromArray($draft->itinerary);
        $price = $draft->hasItinerary() ? app(PricingService::class)->breakdown($draft, $itinerary) : null;

        return view('livewire.trip-builder', [
            'steps' => self::STEPS,
            'draft' => $draft,
            'itinerary' => $itinerary,
            'price' => $price,
            'summary' => $this->summary($draft, $tier, $itinerary, $price),
            ...match ($this->step) {
                1 => $this->stepTierData(),
                2 => ['categories' => Category::withCount(['places' => fn ($q) => $q->published()])->orderBy('id')->get()],
                3 => $this->stepDistrictData(),
                4 => $this->stepPlaceData(),
                5 => ['pointGroups' => TripPoints::grouped(), 'recommended' => $this->recommendedDays()],
                6 => $this->stepStayData($draft, $itinerary),
                7 => $this->stepTransportData(),
                8 => $this->stepReviewData($draft, $itinerary),
                default => [],
            },
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function summary(TripDraft $draft, ?Tier $tier, Itinerary $itinerary, $price): array
    {
        $days = $itinerary->dayCount() ?: $this->days;
        $travellers = $this->travellerCount();
        $estimate = null;
        if (! $price && $tier && $days) {
            $estimate = Money::cents(Setting::get("tier_from_price_{$tier->value}", '0')) * $days * max(1, $this->adults + count($this->childrenAges));
        }

        return [
            'tier' => $tier?->label(),
            'categories' => Category::whereKey($this->categoryIds)->orderBy('id')->pluck('name'),
            'districts' => District::whereKey($this->districtIds)->orderBy('name')->pluck('name'),
            'places' => count($this->placeIds),
            'days' => $days,
            'travellers' => $travellers,
            'start' => $draft->start(),
            'total' => $price ? Money::decimal($price->total) : ($estimate ? Money::decimal($estimate) : null),
            'per_person' => $price ? Money::decimal($price->perPerson) : null,
            'is_estimate_only' => ! $price,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function stepTierData(): array
    {
        return ['tiers' => collect(Tier::cases())->map(fn (Tier $t) => [
            'tier' => $t,
            'from' => Setting::get("tier_from_price_{$t->value}"),
            'text' => match ($t) {
                Tier::Budget => 'Guesthouses and 2-star hotels, a car or van with a chauffeur-guide, bed & breakfast.',
                Tier::Premium => '3–4 star hotels and boutique villas, air-conditioned vehicle, half board.',
                Tier::Luxury => '5-star resorts and heritage bungalows, a national guide, full board and fewer driving hours.',
            },
            'icon' => match ($t) {
                Tier::Budget => 'home',
                Tier::Premium => 'building',
                Tier::Luxury => 'sparkles',
            },
        ])];
    }

    /**
     * @return array<string, mixed>
     */
    private function stepDistrictData(): array
    {
        $allowed = $this->allowedDistrictIds();
        $districts = District::with('province:id,name')
            ->withCount(['places' => fn ($q) => $q->published()])
            ->orderBy('name')->get();

        $covers = Place::published()->inDistricts($allowed->all())
            ->whereHas('media', fn ($q) => $q->where('status', 'published'))
            ->with('cover')->orderByDesc('is_hidden_gem')->get()
            ->groupBy('district_id')->map(fn ($places) => $places->first()->cover);

        return [
            'districts' => $districts,
            'allowed' => $allowed,
            'covers' => $covers,
            'byProvince' => $districts->filter(fn ($d) => $allowed->contains($d->id))->groupBy(fn ($d) => $d->province?->name ?? 'Other'),
            'mapDistricts' => $districts->map(fn ($d) => ['id' => $d->id, 'slug' => $d->slug, 'name' => $d->name, 'allowed' => $allowed->contains($d->id)])->values(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function stepPlaceData(): array
    {
        $districts = District::whereKey($this->districtIds)->orderBy('name')->get();
        $active = $districts->firstWhere('id', $this->activeDistrictId) ?? $districts->first();

        $places = $active
            ? Place::published()->where('district_id', $active->id)
                ->when($this->placeCategoryFilter, fn ($q) => $q->inCategories([$this->placeCategoryFilter]))
                ->with(['categories:id,name,slug', 'cover'])
                ->orderByRaw('CASE WHEN id IN ('.(implode(',', array_map('intval', $this->placeIds)) ?: '0').') THEN 0 ELSE 1 END')
                ->orderByDesc('is_hidden_gem')->orderBy('name')->get()
            : collect();

        $pickedPerDistrict = Place::whereKey($this->placeIds)->selectRaw('district_id, count(*) as total')->groupBy('district_id')->pluck('total', 'district_id');

        return [
            'placeDistricts' => $districts,
            'activeDistrict' => $active,
            'places' => $places,
            'pickedPerDistrict' => $pickedPerDistrict,
            'pickedPlaces' => Place::whereKey($this->placeIds)->with('district:id,name')->orderBy('name')->get(['id', 'name', 'district_id']),
            'nextDistrict' => $active ? $districts->skipUntil(fn ($d) => $d->is($active))->skip(1)->first() : null,
            'filterCategories' => Category::orderBy('id')->get(['id', 'name']),
            'recommended' => $this->recommendedDays(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function stepStayData(TripDraft $draft, Itinerary $itinerary): array
    {
        $suggestions = app(SuggestionService::class);
        $mealPlan = MealPlan::tryFrom($this->mealPlan) ?? MealPlan::BedAndBreakfast;
        $dates = collect($itinerary->days)->pluck('date', 'number');
        $chosen = Hotel::with(['roomRates', 'cover'])->whereKey(array_column($draft->hotels, 'hotel_id'))->get()->keyBy('id');

        $nights = collect($itinerary->overnights())->map(function (array $night, int $day) use ($suggestions, $mealPlan, $dates, $draft, $chosen) {
            $date = $dates[$day] ?? null;
            $options = $suggestions->hotelsFor($night['town'], (float) $night['lat'], (float) $night['lng'], $draft->tierEnum(), $date, $mealPlan, $this->hotelFilters);
            $choice = $draft->hotels[$day] ?? null;
            $selected = $choice ? $chosen->get($choice['hotel_id']) : null;

            if ($selected && ! $options->contains(fn ($o) => $o['hotel']->is($selected))) {
                $options->prepend(['hotel' => $selected, 'rate' => $suggestions->rateFor($selected, $date, $mealPlan)]);
            }

            return [
                'day' => $day,
                'date' => $date,
                'town' => $night['town'],
                'options' => $options,
                'selected' => $selected,
                'rates' => $selected ? $suggestions->ratesFor($selected, $date) : collect(),
                'rate_id' => $choice['room_rate_id'] ?? null,
            ];
        });

        return [
            'nights' => $nights,
            'mealPlans' => collect(MealPlan::cases())->reject(fn ($m) => $m === MealPlan::AllInclusive),
            'cuisines' => Cuisine::orderBy('name')->get(),
            'defaultMealPlan' => $suggestions->defaultMealPlan($draft->tierEnum()),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function stepTransportData(): array
    {
        $suggestions = app(SuggestionService::class);
        $tier = Tier::tryFrom($this->tier) ?? Tier::Premium;
        $travellers = $this->travellerCount();

        $selfDrive = $this->isSelfDrive();

        return [
            'vehicles' => $suggestions->vehicleOptions($travellers, $selfDrive),
            'suggestedVehicle' => $suggestions->vehicleFor($travellers, $this->luggage, $tier, $selfDrive),
            'guideRule' => $suggestions->guideRule($travellers, $tier, $selfDrive),
            'canSelfDrive' => $this->travellerType === 'local',
            'driveMode' => $this->driveMode(),
            'driverOnlyAllowed' => collect($suggestions->guideRule($travellers, $tier)['options'])->contains(fn (GuideType $type) => in_array($type, [GuideType::None, GuideType::Site], true)),
            'selfDriveFits' => Vehicle::selfDrive()->where('max_pax', '>=', $travellers)->exists(),
            'lastDay' => $this->draft()->end(),
            'languages' => $suggestions->guideLanguages(),
            'childSeats' => $this->draft()->childSeats(),
            'refreshmentRate' => (string) Setting::get('refreshments_per_person_day', '0'),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function stepReviewData(TripDraft $draft, Itinerary $itinerary): array
    {
        $hotelIds = array_column($draft->hotels, 'hotel_id');

        return [
            'mapDays' => TripMapData::fromItinerary($draft->itinerary ?? []),
            'start' => TripPoints::find($draft->arrivalPoint),
            'hotels' => Hotel::whereKey($hotelIds)->get()->keyBy('id'),
            'vehicle' => $draft->vehicleId ? Vehicle::find($draft->vehicleId) : null,
            'categoryLabels' => collect(PriceCategory::cases())->mapWithKeys(fn ($c) => [$c->value => $c->label()]),
        ];
    }
}
