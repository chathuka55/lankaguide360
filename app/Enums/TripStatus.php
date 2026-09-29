<?php

namespace App\Enums;

/**
 * Trip lifecycle (SRS 8.8). Only the transitions listed in TRANSITIONS are allowed.
 */
enum TripStatus: string
{
    case Draft = 'draft';
    case Submitted = 'submitted';
    case UnderReview = 'under_review';
    case Approved = 'approved';
    case Rejected = 'rejected';
    case Confirmed = 'confirmed';
    case InProgress = 'in_progress';
    case Completed = 'completed';
    case Cancelled = 'cancelled';

    /**
     * Allowed next statuses. Travellers may cancel any time before the trip is confirmed.
     */
    private const TRANSITIONS = [
        'draft' => ['submitted'],
        'submitted' => ['under_review', 'cancelled'],
        'under_review' => ['under_review', 'approved', 'rejected', 'cancelled'],
        'approved' => ['confirmed', 'cancelled', 'under_review'],
        'rejected' => ['draft'],
        'confirmed' => ['in_progress', 'cancelled'],
        'in_progress' => ['completed'],
        'completed' => [],
        'cancelled' => [],
    ];

    public function label(): string
    {
        return match ($this) {
            self::UnderReview => 'Under review',
            self::InProgress => 'In progress',
            default => ucfirst($this->value),
        };
    }

    public function canTransitionTo(self $to): bool
    {
        return in_array($to->value, self::TRANSITIONS[$this->value], true);
    }

    /**
     * @return array<int, self>
     */
    public function nextStatuses(): array
    {
        return array_map(fn (string $value) => self::from($value), self::TRANSITIONS[$this->value]);
    }

    /** Statuses a traveller can still cancel from. */
    public function travellerCanCancel(): bool
    {
        return in_array($this, [self::Submitted, self::UnderReview, self::Approved], true);
    }

    /** Statuses the agents' request queue works on. */
    public static function open(): array
    {
        return [self::Submitted, self::UnderReview, self::Approved, self::Confirmed, self::InProgress];
    }
}
