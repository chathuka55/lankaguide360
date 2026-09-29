<?php

namespace App\Services\Routing;

/**
 * One drive between two points.
 */
final class Leg
{
    /**
     * @param  array<int, array{0: float, 1: float}>  $geometry  [lat, lng] points along the road
     */
    public function __construct(
        public readonly float $km,
        public readonly int $minutes,
        public readonly array $geometry,
        public readonly string $provider,
    ) {}

    public static function none(float $lat, float $lng): self
    {
        return new self(0.0, 0, [[$lat, $lng]], 'none');
    }
}
