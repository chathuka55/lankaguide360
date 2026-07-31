<?php
/**
 * ResourceMatcher — turns a scored destination itinerary (from TripRecommender)
 * into a bookable, priced package by selecting a guide, a driver, a vehicle and
 * hotels, all in LKR.
 *
 * Selection rules mirror the real business model:
 *  - Guides can be regional or island-wide; multi-region trips prefer an
 *    island-wide guide, single-region trips prefer a matching regional guide.
 *  - Vehicles must seat the whole party; we prefer the cheapest suitable vehicle
 *    within the budget tier and (when possible) in the trip's main region.
 *  - Some guides/drivers own their vehicle; if the chosen vehicle is owned by a
 *    driver, that driver is assigned automatically. Self-drive vehicles
 *    (has_driver = 0) still get a driver proposed unless the guide drives.
 *  - Hotels are chosen per region visited, matched to the budget tier.
 *
 * Everything is transparent/rule-based so the quote can be explained to the
 * customer and adjusted by a manager later.
 */

class ResourceMatcher
{
    private PDO $db;

    // Rough language-friendliness ordering could be added later; kept simple here.
    private array $budgetOrder = ['low' => 0, 'medium' => 1, 'high' => 2];

    public function __construct(PDO $db)
    {
        $this->db = $db;
    }

    /**
     * @param array  $planResult  output of TripRecommender::recommend()
     * @param string $budgetTier  low|medium|high
     * @param int    $days
     * @param int    $travelers
     * @param string|null $preferredLanguage  e.g. 'English'
     * @return array  guide, driver, vehicle, hotels[], pricing[], notes[]
     */
    public function match(array $planResult, string $budgetTier, int $days, int $travelers, ?string $preferredLanguage = 'English'): array
    {
        $days = max(1, $days);
        $travelers = max(1, $travelers);

        // Distinct regions across the itinerary, in first-seen order.
        $regions = [];
        foreach ($planResult['items'] as $item) {
            $r = $item['destination']['region'] ?? null;
            if ($r && !in_array($r, $regions, true)) {
                $regions[] = $r;
            }
        }
        $mainRegion = $regions[0] ?? null;
        $multiRegion = count($regions) > 1;

        $notes = [];

        $guide   = $this->pickGuide($mainRegion, $multiRegion, $preferredLanguage, $notes);
        $vehicle = $this->pickVehicle($mainRegion, $travelers, $budgetTier, $notes);
        $driver  = $this->pickDriver($vehicle, $guide, $mainRegion, $notes);
        $hotels  = $this->pickHotels($regions, $budgetTier, $days, $notes);

        $pricing = $this->price($planResult, $guide, $driver, $vehicle, $hotels, $days, $travelers);

        return [
            'guide'    => $guide,
            'driver'   => $driver,
            'vehicle'  => $vehicle,
            'hotels'   => $hotels,
            'regions'  => $regions,
            'pricing'  => $pricing,
            'notes'    => $notes,
        ];
    }

    private function pickGuide(?string $mainRegion, bool $multiRegion, ?string $lang, array &$notes): ?array
    {
        // Island-wide guide for multi-region trips; regional guide otherwise.
        if ($multiRegion) {
            $stmt = $this->db->prepare(
                "SELECT * FROM guides WHERE is_active = 1 AND is_regional = 0
                 ORDER BY (languages LIKE ?) DESC, rating DESC LIMIT 1"
            );
            $stmt->execute(['%' . ($lang ?: 'English') . '%']);
            if ($g = $stmt->fetch()) {
                $notes[] = "Island-wide guide {$g['full_name']} suits this multi-region route.";
                return $g;
            }
        }
        // Regional guide matching the main region.
        $stmt = $this->db->prepare(
            "SELECT * FROM guides WHERE is_active = 1 AND (region = ? OR is_regional = 0)
             ORDER BY (region = ?) DESC, (languages LIKE ?) DESC, rating DESC LIMIT 1"
        );
        $stmt->execute([$mainRegion, $mainRegion, '%' . ($lang ?: 'English') . '%']);
        $g = $stmt->fetch() ?: null;
        if ($g) {
            $notes[] = "Guide {$g['full_name']} ({$g['region']}) speaks {$g['languages']}.";
        }
        return $g;
    }

    private function pickVehicle(?string $mainRegion, int $travelers, string $budgetTier, array &$notes): ?array
    {
        // Cheapest rentable vehicle that seats the party; prefer region + budget fit.
        $stmt = $this->db->prepare(
            "SELECT * FROM vehicles
             WHERE is_active = 1 AND is_rentable = 1 AND seats >= ?
             ORDER BY (region = ?) DESC, rate_per_day_lkr ASC LIMIT 1"
        );
        $stmt->execute([$travelers, $mainRegion]);
        $v = $stmt->fetch();
        if (!$v) {
            // Fall back to the largest available vehicle if none seats everyone.
            $v = $this->db->query("SELECT * FROM vehicles WHERE is_active=1 AND is_rentable=1 ORDER BY seats DESC LIMIT 1")->fetch() ?: null;
            if ($v) {
                $notes[] = "No single vehicle seats {$travelers}; suggested the largest ({$v['seats']} seats) — a second vehicle may be needed.";
            }
        } else {
            $notes[] = "Vehicle {$v['name']} ({$v['seats']} seats) fits your group of {$travelers}.";
        }
        return $v;
    }

    private function pickDriver(?array $vehicle, ?array $guide, ?string $mainRegion, array &$notes): ?array
    {
        // If the vehicle is owned by a driver, that driver comes with it.
        if ($vehicle && $vehicle['owner_type'] === 'driver' && $vehicle['owner_ref_id']) {
            $stmt = $this->db->prepare("SELECT * FROM drivers WHERE id = ? AND is_active = 1");
            $stmt->execute([$vehicle['owner_ref_id']]);
            if ($d = $stmt->fetch()) {
                $notes[] = "Driver {$d['full_name']} owns the selected vehicle.";
                return $d;
            }
        }
        // If a guide owns the vehicle and drives it, no separate driver needed.
        if ($vehicle && $guide && $vehicle['owner_type'] === 'guide' && (int)$vehicle['owner_ref_id'] === (int)$guide['id']) {
            $notes[] = "Your guide {$guide['full_name']} also drives their own vehicle — no separate driver needed.";
            return null;
        }
        // Otherwise pick a regional driver.
        $stmt = $this->db->prepare(
            "SELECT * FROM drivers WHERE is_active = 1
             ORDER BY (region = ?) DESC, rating DESC, daily_rate_lkr ASC LIMIT 1"
        );
        $stmt->execute([$mainRegion]);
        $d = $stmt->fetch() ?: null;
        if ($d) {
            $notes[] = "Driver {$d['full_name']} assigned for the route.";
        }
        return $d;
    }

    /** One hotel per region visited, matched to budget tier, with allocated nights. */
    private function pickHotels(array $regions, string $budgetTier, int $days, array &$notes): array
    {
        if (!$regions) {
            return [];
        }
        $nightsTotal = max(1, $days - 1); // last day is departure
        $per = (int)floor($nightsTotal / count($regions));
        $remainder = $nightsTotal - ($per * count($regions));

        $hotels = [];
        foreach ($regions as $i => $region) {
            $stmt = $this->db->prepare(
                "SELECT * FROM hotels WHERE is_active = 1 AND region = ?
                 ORDER BY (budget_tier = ?) DESC, ABS(star_rating - 3) ASC, rating DESC LIMIT 1"
            );
            $stmt->execute([$region, $budgetTier]);
            $h = $stmt->fetch();
            if (!$h) {
                // No hotel in that region — fall back to any budget-matched hotel.
                $stmt = $this->db->prepare(
                    "SELECT * FROM hotels WHERE is_active=1 ORDER BY (budget_tier=?) DESC, rating DESC LIMIT 1"
                );
                $stmt->execute([$budgetTier]);
                $h = $stmt->fetch();
            }
            if ($h) {
                $nights = $per + ($i < $remainder ? 1 : 0);
                $nights = max(1, $nights);
                $h['nights'] = $nights;
                $hotels[] = $h;
            }
        }
        if ($hotels) {
            $notes[] = count($hotels) . ' hotel(s) selected across ' . count($regions) . ' region(s).';
        }
        return $hotels;
    }

    /** Build a transparent LKR price breakdown. */
    private function price(array $planResult, ?array $guide, ?array $driver, ?array $vehicle, array $hotels, int $days, int $travelers): array
    {
        $rooms = (int)ceil($travelers / 2);

        $guideCost   = $guide   ? (float)$guide['daily_rate_lkr']   * $days : 0.0;
        $driverCost  = $driver  ? (float)$driver['daily_rate_lkr']  * $days : 0.0;
        $vehicleCost = $vehicle ? (float)$vehicle['rate_per_day_lkr'] * $days : 0.0;

        $hotelCost = 0.0;
        foreach ($hotels as $h) {
            $hotelCost += (float)$h['price_per_night_lkr'] * (int)$h['nights'] * $rooms;
        }

        $entryFees = 0.0;
        foreach ($planResult['items'] as $item) {
            $entryFees += (float)($item['destination']['entry_fee_lkr'] ?? 0) * $travelers;
        }

        $subtotal = $guideCost + $driverCost + $vehicleCost + $hotelCost + $entryFees;
        $serviceFee = round($subtotal * 0.05, 2); // 5% platform service fee
        $total = $subtotal + $serviceFee;

        return [
            'rooms'        => $rooms,
            'guide'        => $guideCost,
            'driver'       => $driverCost,
            'vehicle'      => $vehicleCost,
            'hotel'        => $hotelCost,
            'entry_fees'   => $entryFees,
            'service_fee'  => $serviceFee,
            'subtotal'     => $subtotal,
            'total'        => $total,
        ];
    }
}
