<?php

namespace App\Services\Itinerary;

/**
 * Result of ItineraryService::generate(): days with timed stops, places that had to be
 * dropped and warnings. Stored in the TripDraft as a plain array (toArray()).
 */
final class Itinerary
{
    /**
     * @param  array<int, array<string, mixed>>  $days
     * @param  array<int, array{place_id: int, name: string, reason: string}>  $dropped
     * @param  array<int, string>  $warnings
     */
    public function __construct(
        public array $days = [],
        public array $dropped = [],
        public array $warnings = [],
    ) {}

    /**
     * @param  array<string, mixed>|null  $data
     */
    public static function fromArray(?array $data): self
    {
        return new self($data['days'] ?? [], $data['dropped'] ?? [], $data['warnings'] ?? []);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'days' => $this->days,
            'dropped' => $this->dropped,
            'warnings' => $this->warnings,
            'totals' => [
                'days' => count($this->days),
                'nights' => max(0, count($this->days) - 1),
                'drive_km' => round(array_sum(array_column($this->days, 'drive_km')), 1),
                'drive_minutes' => array_sum(array_column($this->days, 'drive_minutes')),
                'stops' => array_sum(array_map(fn ($day) => count($day['stops']), $this->days)),
            ],
        ];
    }

    public function dayCount(): int
    {
        return count($this->days);
    }

    public function totalKm(): float
    {
        return round(array_sum(array_column($this->days, 'drive_km')), 1);
    }

    /**
     * @return array<int, int> place ids in visiting order
     */
    public function placeIds(): array
    {
        return array_merge(...array_map(fn ($day) => array_column($day['stops'], 'place_id'), $this->days ?: [['stops' => []]]));
    }

    /**
     * Overnight towns keyed by day number (days without an overnight stay are omitted).
     *
     * @return array<int, array{town: string, lat: float, lng: float, district_id: ?int}>
     */
    public function overnights(): array
    {
        $nights = [];
        foreach ($this->days as $day) {
            if (! empty($day['overnight'])) {
                $nights[$day['number']] = $day['overnight'];
            }
        }

        return $nights;
    }
}
