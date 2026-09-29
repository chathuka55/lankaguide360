<?php

namespace App\Services\Import;

use App\Models\District;
use App\Support\Geo;
use Illuminate\Support\Facades\File;

/**
 * Downloads Sri Lanka's 25 district boundaries (geoBoundaries gbOpen ADM2, ODbL), saves them
 * to public/geo/lk-districts.geojson for the maps, and updates district centroids.
 */
class GeoBoundariesImporter extends Importer
{
    public const ATTRIBUTION = 'District boundaries: geoBoundaries (gbOpen LKA ADM2), © OpenStreetMap contributors, ODbL';

    public function key(): string
    {
        return 'geoboundaries';
    }

    protected function handle(array $options): void
    {
        $meta = $this->client->get(config('lankaguide.import.geoboundaries_api'), [], ['group' => 'geoboundaries'])->json();

        if (! isset($meta['gjDownloadURL'])) {
            throw new ImportException('geoBoundaries API did not return a GeoJSON download URL.');
        }

        $download = $this->client->get($meta['gjDownloadURL'], [], ['group' => 'geoboundaries']);

        // Large files slow the builder map; geoBoundaries publishes a simplified version too.
        if (strlen($download->body) > config('lankaguide.import.geojson_max_bytes') && isset($meta['simplifiedGeometryGeoJSON'])) {
            $this->report->note('Full GeoJSON is larger than the limit; using the simplified version.');
            $download = $this->client->get($meta['simplifiedGeometryGeoJSON'], [], ['group' => 'geoboundaries']);
        }

        $collection = $download->json();

        if (! $download->ok() || ! isset($collection['features'])) {
            throw new ImportException("Could not download district GeoJSON (HTTP {$download->status}).");
        }

        $districts = District::all()->keyBy('slug');
        $features = [];

        $this->progress->start(count($collection['features']), 'Districts');

        foreach ($collection['features'] as $feature) {
            $name = $feature['properties']['shapeName'] ?? '';
            $slug = DistrictGeometry::matchSlug($name, $districts->keys()->all());

            if ($slug === null || ! isset($feature['geometry'])) {
                $this->report->failed++;
                $this->log('failed', null, "No district matches boundary [{$name}].");
                $this->progress->advance();

                continue;
            }

            $district = $districts[$slug];
            $geometry = Geo::roundGeometry($feature['geometry']);
            $centroid = Geo::centroid($geometry);

            $features[] = [
                'type' => 'Feature',
                'properties' => [
                    'slug' => $slug,
                    'name' => $district->name,
                    'district_id' => $district->id,
                    'iso' => $feature['properties']['shapeISO'] ?? null,
                ],
                'geometry' => $geometry,
            ];

            if ($centroid) {
                $district->forceFill(['lat' => round($centroid['lat'], 6), 'lng' => round($centroid['lng'], 6)])->save();
            }

            $this->report->updated++;
            $this->log('updated', $district, "Boundary matched from [{$name}]; centroid updated.");
            $this->progress->advance();
        }

        $this->progress->finish();

        $missing = $districts->keys()->diff(collect($features)->pluck('properties.slug'));
        foreach ($missing as $slug) {
            $this->log('not_found', $districts[$slug], 'No boundary found for this district.');
        }

        File::ensureDirectoryExists(dirname(config('lankaguide.import.geojson_path')));
        File::put(config('lankaguide.import.geojson_path'), json_encode([
            'type' => 'FeatureCollection',
            'attribution' => self::ATTRIBUTION,
            'source' => $meta['staticDownloadLink'] ?? $meta['gjDownloadURL'],
            'features' => $features,
        ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));

        $this->report->note(sprintf('Saved %d district shapes to %s', count($features), config('lankaguide.import.geojson_path')));
    }
}
