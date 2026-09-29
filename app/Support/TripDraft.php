<?php

namespace App\Support;

use App\Enums\Tier;
use Illuminate\Contracts\Session\Session;
use Illuminate\Support\Carbon;

/**
 * Everything the traveller has chosen in the Trip Builder, kept in the session between
 * steps (FR-27) and copied into the trips tables on submit. Plain data only.
 */
final class TripDraft
{
    public const SESSION_KEY = 'trip_draft';

    public ?string $tier = null;

    /** @var array<int, int> */
    public array $categoryIds = [];

    /** @var array<int, int> */
    public array $districtIds = [];

    /** @var array<int, int> */
    public array $placeIds = [];

    public ?string $startDate = null;

    public ?int $days = null;

    public int $adults = 2;

    /** @var array<int, int> ages of children (2–17) */
    public array $childrenAges = [];

    public int $infants = 0;

    public string $arrivalPoint = 'BIA Katunayake';

    public string $departurePoint = 'BIA Katunayake';

    public ?int $luggage = null;

    /** @var array<string, mixed>|null generated itinerary (Itinerary::toArray()) */
    public ?array $itinerary = null;

    /** @var array<int, array{hotel_id: int, room_rate_id: ?int}> keyed by day number */
    public array $hotels = [];

    public ?string $mealPlan = null;

    /** @var array<int, int> */
    public array $cuisineIds = [];

    public ?string $dietaryNotes = null;

    public ?int $vehicleId = null;

    public string $guideType = 'chauffeur';

    public string $guideLanguage = 'English';

    public ?int $guideId = null;

    public ?string $specialRequests = null;

    /** Highest step the traveller has completed (1–8). */
    public int $completedStep = 0;

    /** Package the draft was copied from, if any. */
    public ?int $packageId = null;

    public static function fromSession(Session $session): self
    {
        return self::fromArray((array) $session->get(self::SESSION_KEY, []));
    }

    public function saveTo(Session $session): void
    {
        $session->put(self::SESSION_KEY, $this->toArray());
    }

    public static function forget(Session $session): void
    {
        $session->forget(self::SESSION_KEY);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        $draft = new self;

        foreach (get_object_vars($draft) as $property => $default) {
            $key = self::snake($property);
            if (array_key_exists($key, $data)) {
                $draft->{$property} = $data[$key];
            }
        }

        // "Add to my trip" on place pages writes trip_draft.place_ids directly.
        $draft->placeIds = array_values(array_unique(array_map('intval', $draft->placeIds)));
        $draft->categoryIds = array_values(array_map('intval', $draft->categoryIds));
        $draft->districtIds = array_values(array_map('intval', $draft->districtIds));
        $draft->childrenAges = array_values(array_map('intval', $draft->childrenAges));

        return $draft;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $data = [];
        foreach (get_object_vars($this) as $property => $value) {
            $data[self::snake($property)] = $value;
        }

        return $data;
    }

    public function tierEnum(): Tier
    {
        return Tier::tryFrom((string) $this->tier) ?? Tier::Premium;
    }

    public function children(): int
    {
        return count($this->childrenAges);
    }

    /** Everyone in the group, including infants (vehicle seats, SRS 5.5). */
    public function travellers(): int
    {
        return $this->adults + $this->children() + $this->infants;
    }

    /** Children under 4 need a child seat (FR-12). */
    public function childSeats(): int
    {
        return count(array_filter($this->childrenAges, fn (int $age) => $age < 4)) + $this->infants;
    }

    public function start(): ?Carbon
    {
        return $this->startDate ? Carbon::parse($this->startDate)->startOfDay() : null;
    }

    public function nights(): int
    {
        return max(0, (int) $this->days - 1);
    }

    public function hasItinerary(): bool
    {
        return ! empty($this->itinerary['days']);
    }

    private static function snake(string $property): string
    {
        return match ($property) {
            'placeIds' => 'place_ids',
            default => strtolower((string) preg_replace('/(?<!^)[A-Z]/', '_$0', $property)),
        };
    }
}
