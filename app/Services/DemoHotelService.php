<?php

namespace App\Services;

use App\Enums\MealPlan;
use App\Enums\PublishStatus;
use App\Enums\Tier;
use App\Models\District;
use App\Models\Hotel;
use App\Models\Place;
use App\Models\RoomRate;
use App\Models\TripDay;
use App\Support\Geo;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Fictional, clearly flagged hotels (is_demo) so the Trip Builder can be demonstrated anywhere in
 * Sri Lanka before real hotels and rates are entered: every district capital and tourist base in
 * data/demo_towns.php, plus a town near any place that is more than 20 km from all of them.
 * Prices are placeholder estimates in USD. Deterministic: names and prices depend only on the town.
 */
class DemoHotelService
{
    /** A place further than this from every demo town gets a demo town of its own. */
    public const COVERAGE_KM = 20;

    /** Hotels per town and tier. */
    public const PER_TIER = ['budget' => 2, 'premium' => 2, 'luxury' => 1];

    private const NAMES = [
        'coastal' => ['Sea Breeze', 'Coral Bay', 'Palm Shore', 'Blue Lagoon', 'Sunset Sands', 'Moonstone', 'Turtle Cove', 'Ocean Pearl'],
        'inland' => ['Green Valley', 'Misty Hills', 'Lotus Garden', 'Golden Mango', 'Lake View', 'Jungle Edge', 'Tea Garden', 'Spice Garden', 'Rock View', 'Paddy Field'],
    ];

    private const TYPES = [
        'budget' => [['Guest House', 'guest_house'], ['Homestay', 'guest_house'], ['Inn', 'hotel'], ['Lodge', 'guest_house']],
        'premium' => [['Hotel', 'hotel'], ['Boutique Hotel', 'hotel'], ['Villa', 'hotel'], ['Resort', 'resort']],
        'luxury' => [['Resort & Spa', 'resort'], ['Heritage Bungalow', 'hotel'], ['Luxury Villas', 'resort']],
    ];

    /**
     * Room types per tier: [room type, base price per night in USD (BB), sleeps, meal plans].
     */
    private const ROOMS = [
        'budget' => [['Standard double', 28, 2, ['RO', 'BB', 'HB']], ['Family room', 42, 4, ['BB', 'HB']]],
        'premium' => [['Deluxe double', 85, 2, ['BB', 'HB', 'FB']], ['Superior room', 115, 2, ['BB', 'HB', 'FB']]],
        'luxury' => [['Deluxe view room', 230, 2, ['BB', 'HB', 'FB']], ['Suite', 340, 3, ['BB', 'HB', 'FB']]],
    ];

    /** Added to the BB price per room for half board / full board / room only. */
    private const MEAL_SUPPLEMENT = [
        'budget' => ['RO' => -6, 'BB' => 0, 'HB' => 12, 'FB' => 22],
        'premium' => ['RO' => -15, 'BB' => 0, 'HB' => 30, 'FB' => 50],
        'luxury' => ['RO' => -30, 'BB' => 0, 'HB' => 60, 'FB' => 100],
    ];

    /**
     * The towns that get demo hotels.
     *
     * @return Collection<int, array{town: string, district_id: int, lat: float, lng: float, coastal: bool, auto: bool}>
     */
    public function towns(): Collection
    {
        $districts = District::pluck('id', 'slug');

        $towns = collect(require database_path('seeders/data/demo_towns.php'))
            ->filter(fn ($t) => isset($districts[$t[1]]))
            ->map(fn ($t) => ['town' => $t[0], 'district_id' => (int) $districts[$t[1]], 'lat' => $t[2], 'lng' => $t[3], 'coastal' => $t[4], 'auto' => false])
            ->values();

        // Cover every place we know the location of (published or not), nearest-first.
        foreach (Place::whereNotNull('lat')->whereNotNull('lng')->with('district:id,name')->orderBy('id')->get() as $place) {
            $nearest = $towns->min(fn ($t) => Geo::distanceKm($t['lat'], $t['lng'], (float) $place->lat, (float) $place->lng));
            if ($nearest > self::COVERAGE_KM) {
                $towns->push([
                    'town' => $this->townNameFor($place),
                    'district_id' => (int) $place->district_id,
                    'lat' => (float) $place->lat,
                    'lng' => (float) $place->lng,
                    'coastal' => false,
                    'auto' => true,
                ]);
            }
        }

        return $towns->unique('town')->values();
    }

    /**
     * Create the demo hotels that don't exist yet.
     *
     * @return array{towns: int, hotels: int, rates: int}
     */
    public function create(): array
    {
        $created = ['towns' => 0, 'hotels' => 0, 'rates' => 0];

        DB::transaction(function () use (&$created) {
            foreach ($this->towns() as $town) {
                $created['towns']++;
                $seed = crc32($town['town']);
                $names = self::NAMES[$town['coastal'] ? 'coastal' : 'inland'];
                $n = 0;

                foreach (self::PER_TIER as $tier => $count) {
                    for ($i = 0; $i < $count; $i++, $n++) {
                        [$typeLabel, $type] = self::TYPES[$tier][($seed + $i) % count(self::TYPES[$tier])];
                        $name = $names[($seed + $n) % count($names)].' '.$typeLabel.' '.$town['town'];
                        $slug = Str::slug($name).'-demo';

                        if (Hotel::where('slug', $slug)->exists()) {
                            continue;
                        }

                        $hotel = new Hotel;
                        $hotel->forceFill([
                            'district_id' => $town['district_id'],
                            'town' => $town['town'],
                            'name' => $name,
                            'slug' => $slug,
                            'type' => $type,
                            'tier' => $tier,
                            'star_rating' => match ($tier) {
                                'budget' => 2, 'premium' => 3 + ($seed + $i) % 2, default => 5
                            },
                            'kid_friendly' => $tier !== 'budget' || ($seed + $i) % 2 === 0,
                            // A few hundred metres apart, around the town centre.
                            'lat' => round($town['lat'] + ((($seed >> ($n + 1)) % 9) - 4) * 0.0015, 6),
                            'lng' => round($town['lng'] + ((($seed >> ($n + 3)) % 9) - 4) * 0.0015, 6),
                            'amenities' => $this->amenities($tier, $town['coastal'], $seed + $n),
                            'is_active' => true,
                            'status' => PublishStatus::Published,
                            'is_demo' => true,
                            'address' => 'Demo listing for '.$town['town'].' — not a real hotel',
                        ])->save();
                        $created['hotels']++;

                        foreach (self::ROOMS[$tier] as $r => [$roomType, $base, $sleeps, $plans]) {
                            // Up to ±15% around the base price so hotels in a town differ.
                            $price = round($base * (0.85 + (($seed + $n * 7 + $r) % 31) / 100), 0);
                            foreach ($plans as $plan) {
                                RoomRate::create([
                                    'hotel_id' => $hotel->id,
                                    'room_type' => $roomType,
                                    'meal_plan' => MealPlan::from($plan),
                                    'price_per_night' => max(10, $price + self::MEAL_SUPPLEMENT[$tier][$plan]),
                                    'max_occupancy' => $sleeps,
                                    'extra_bed_price' => match ($tier) {
                                        'budget' => 10, 'premium' => 25, default => 60
                                    },
                                    'is_estimate' => true,
                                ]);
                                $created['rates']++;
                            }
                        }
                    }
                }
            }
        });

        return $created;
    }

    /**
     * Delete every demo hotel and its rates. Trip days that used one keep their town but lose the
     * hotel, so an agent can choose a real one.
     */
    public function remove(): int
    {
        return DB::transaction(function () {
            $ids = Hotel::where('is_demo', true)->pluck('id');
            TripDay::whereIn('hotel_id', $ids)->update(['hotel_id' => null, 'room_rate_id' => null]);

            return Hotel::whereKey($ids)->delete();
        });
    }

    /**
     * @return array<int, string>
     */
    private function amenities(string $tier, bool $coastal, int $seed): array
    {
        $list = ['wifi', 'parking', 'restaurant'];
        if ($tier !== 'budget') {
            array_push($list, 'air_conditioning', 'pool', 'bar', 'room_service');
        }
        if ($tier === 'luxury') {
            array_push($list, 'spa', 'gym', 'kids_club');
        }
        if ($coastal && $seed % 3 !== 0) {
            $list[] = 'beach_front';
        }

        return $list;
    }

    /**
     * The nearest real town name from the OpenStreetMap hotels (within 12 km), otherwise the
     * place name without words like "National Park" ("Yala National Park" → "Yala").
     */
    private function townNameFor(Place $place): string
    {
        $osm = Hotel::query()->where('is_demo', false)->whereNotNull('town')->where('town', '!=', '')
            ->nearTo((float) $place->lat, (float) $place->lng, 12)
            ->orderBy('distance_km')->value('town');

        if ($osm) {
            // OpenStreetMap towns are sometimes lower case or carry an admin suffix ("Katharagama UP").
            $town = trim((string) preg_replace('/\s+(UP|PS|DS|MC|UC)$/', '', Str::title(trim($osm))));
            if ($town !== '' && mb_strlen($town) <= 40) {
                return $town;
            }
        }

        $name = preg_replace('/\(.*?\)/', '', $place->name);
        $name = preg_replace('/\b(National Park|Beach|Water ?falls?|Falls|Rock Fortress|Rock|Temple|Kovil|Forest Reserve|Rainforest Reserve|Reserve|Sanctuary|Wetland|Lighthouse|Fort|Statue|Buddha|Viharaya|Vihara|Rajamaha|Purana|Gedige|Mountain Range|Range|Ruins|Ancient|Hill|Shrine of Our Lady of|Safari Park|Cape|Seya|Hela|Ella|Wewa)\b/i', '', (string) $name);
        $name = trim((string) preg_replace('/\s+/', ' ', (string) $name), " -'");

        return $name !== '' ? Str::limit($name, 40, '') : $place->name;
    }
}
