<?php

namespace App\Services;

use App\Models\RouteCache;
use App\Services\Routing\Leg;
use App\Support\Geo;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Road distance and drive time between two points (SRS 5.3, 6.4).
 *
 * With ORS_API_KEY set, legs come from OpenRouteService (called from the backend only);
 * otherwise, or if the API fails, a free offline estimate is used: straight-line distance ×
 * road factor at 45 km/h (30 km/h in the hill country). Every leg is cached in route_cache,
 * so repeat plans cost no API calls; estimates are replaced once a key is configured.
 */
class RoutingService
{
    /** Coordinates are rounded for the cache key (4 decimals ≈ 11 m). */
    private const PRECISION = 4;

    public function leg(float $fromLat, float $fromLng, float $toLat, float $toLng): Leg
    {
        [$fromLat, $fromLng, $toLat, $toLng] = array_map(fn ($v) => round($v, self::PRECISION), [$fromLat, $fromLng, $toLat, $toLng]);

        if ($fromLat === $toLat && $fromLng === $toLng) {
            return Leg::none($fromLat, $fromLng);
        }

        $cached = RouteCache::where(['from_lat' => $fromLat, 'from_lng' => $fromLng, 'to_lat' => $toLat, 'to_lng' => $toLng])->first();

        if ($cached && ($cached->provider !== 'estimate' || ! $this->usesApi())) {
            return new Leg((float) $cached->km, (int) $cached->minutes, $cached->geometry ?? [[$fromLat, $fromLng], [$toLat, $toLng]], $cached->provider);
        }

        $leg = ($this->usesApi() ? $this->fromOpenRouteService($fromLat, $fromLng, $toLat, $toLng) : null)
            ?? $this->estimate($fromLat, $fromLng, $toLat, $toLng);

        RouteCache::updateOrCreate(
            ['from_lat' => $fromLat, 'from_lng' => $fromLng, 'to_lat' => $toLat, 'to_lng' => $toLng],
            ['km' => round($leg->km, 1), 'minutes' => $leg->minutes, 'geometry' => $leg->geometry, 'provider' => $leg->provider],
        );

        return $leg;
    }

    public function usesApi(): bool
    {
        return config('lankaguide.routing.provider') === 'openrouteservice' && filled(config('lankaguide.routing.ors_key'));
    }

    /**
     * Free offline estimate (NFR-14): haversine × road factor, slower in the hills.
     */
    public function estimate(float $fromLat, float $fromLng, float $toLat, float $toLng): Leg
    {
        $km = Geo::distanceKm($fromLat, $fromLng, $toLat, $toLng) * (float) config('lankaguide.routing.road_factor');
        $hilly = $this->inHills($fromLat, $fromLng) || $this->inHills($toLat, $toLng);
        $speed = (float) config($hilly ? 'lankaguide.routing.hill_speed_kmh' : 'lankaguide.routing.speed_kmh');

        return new Leg(
            round($km, 1),
            max(5, (int) round($km / $speed * 60)),
            [[$fromLat, $fromLng], [$toLat, $toLng]],
            'estimate',
        );
    }

    /**
     * Clear every cached leg (admin "clear route cache").
     */
    public function clearCache(): int
    {
        return RouteCache::query()->delete();
    }

    private function inHills(float $lat, float $lng): bool
    {
        $box = config('lankaguide.routing.hill_box');

        return $lat >= $box['south'] && $lat <= $box['north'] && $lng >= $box['west'] && $lng <= $box['east'];
    }

    private function fromOpenRouteService(float $fromLat, float $fromLng, float $toLat, float $toLng): ?Leg
    {
        try {
            $response = Http::withUserAgent(config('lankaguide.user_agent'))
                ->withHeaders(['Authorization' => config('lankaguide.routing.ors_key')])
                ->acceptJson()
                ->timeout(15)
                ->retry(2, 1000, throw: false)
                ->post(config('lankaguide.routing.ors_url'), [
                    'coordinates' => [[$fromLng, $fromLat], [$toLng, $toLat]],
                ]);

            $feature = $response->json('features.0');

            if (! $response->successful() || ! isset($feature['properties']['summary'])) {
                Log::warning('OpenRouteService failed, using estimate', ['status' => $response->status()]);

                return null;
            }

            $summary = $feature['properties']['summary'];
            $points = collect($feature['geometry']['coordinates'] ?? [])
                ->map(fn (array $c) => [round($c[1], 5), round($c[0], 5)]);

            return new Leg(
                round(($summary['distance'] ?? 0) / 1000, 1),
                max(1, (int) round(($summary['duration'] ?? 0) / 60)),
                // Keep the cached geometry light: at most ~200 points per leg.
                $points->nth(max(1, (int) ceil($points->count() / 200)))->push($points->last())->values()->all(),
                'openrouteservice',
            );
        } catch (Throwable $e) {
            Log::warning('OpenRouteService error, using estimate: '.$e->getMessage());

            return null;
        }
    }
}
