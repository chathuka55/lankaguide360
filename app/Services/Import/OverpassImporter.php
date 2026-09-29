<?php

namespace App\Services\Import;

use App\Models\District;
use Illuminate\Support\Collection;
use Throwable;

/**
 * Shared Overpass (OpenStreetMap, ODbL) logic: one query per district bounding box,
 * then results are kept only if they fall inside that district's polygon.
 */
abstract class OverpassImporter extends Importer
{
    public const ATTRIBUTION = '© OpenStreetMap contributors, ODbL';

    public function __construct(ImportClient $client, protected DistrictGeometry $geometry)
    {
        parent::__construct($client);
    }

    /**
     * Overpass QL statements for one district; `{{bbox}}` is replaced with "south,west,north,east".
     */
    abstract protected function statements(): string;

    abstract protected function importDistrict(District $district, Collection $elements): void;

    /**
     * Options: district (slug), fresh.
     */
    protected function handle(array $options): void
    {
        $districts = District::query()
            ->when($options['district'] ?? null, fn ($query, $slug) => $query->where('slug', $slug))
            ->orderBy('name')
            ->get();

        $this->progress->start($districts->count(), 'Districts');

        foreach ($districts as $district) {
            try {
                $elements = $this->fetch($district);
                $this->importDistrict($district, $elements);
            } catch (Throwable $e) {
                $this->report->failed++;
                $this->log('failed', $district, $e->getMessage());
            }

            $this->progress->advance();
        }

        $this->progress->finish();
    }

    /**
     * @return Collection<int, array> elements inside the district, each with `lat`/`lng` resolved
     */
    protected function fetch(District $district): Collection
    {
        $bbox = $this->geometry->bbox($district)
            ?? throw new ImportException("No boundary for {$district->name}; run lg:import-districts.");

        $box = implode(',', array_map(fn ($v) => round($v, 5), [$bbox['south'], $bbox['west'], $bbox['north'], $bbox['east']]));
        $query = '[out:json][timeout:120];('.str_replace('{{bbox}}', $box, $this->statements()).');out center tags;';

        $response = $this->client->post(config('lankaguide.import.overpass_url'), ['data' => $query], [
            'group' => 'overpass',
            'delay_ms' => config('lankaguide.import.delay_ms.overpass'),
            // Overpass answers 429/504 when busy: back off longer than the default.
            'backoff_ms' => [5000, 20000],
        ]);

        if (! $response->ok() || ($data = $response->json()) === null) {
            throw new ImportException("Overpass returned HTTP {$response->status} for {$district->name}; try again later or set OVERPASS_URL to a mirror.");
        }

        return collect($data['elements'] ?? [])
            ->map(function (array $element) {
                $element['lat'] ??= $element['center']['lat'] ?? null;
                $element['lng'] = $element['lon'] ?? $element['center']['lon'] ?? null;

                return $element;
            })
            ->filter(fn (array $element) => $element['lat'] !== null && $element['lng'] !== null)
            ->filter(fn (array $element) => $this->geometry->contains($district, (float) $element['lat'], (float) $element['lng']))
            ->values();
    }

    protected function osmId(array $element): string
    {
        return $element['type'].'/'.$element['id'];
    }

    protected function name(array $element): ?string
    {
        $name = trim($element['tags']['name:en'] ?? $element['tags']['name'] ?? '');

        return $name !== '' ? $name : null;
    }
}
