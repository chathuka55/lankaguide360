<?php

namespace Tests\Feature\Import;

use App\Support\Geo;
use Database\Seeders\CategorySeeder;
use Database\Seeders\LocationSeeder;
use Database\Seeders\PlaceSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Sleep;
use Tests\TestCase;

/**
 * Importer tests: no real network (stray requests fail), no real sleeping, a throwaway
 * response cache and GeoJSON path, and a fake public disk for images.
 */
abstract class ImportTestCase extends TestCase
{
    use RefreshDatabase;

    protected string $workDir;

    protected function setUp(): void
    {
        parent::setUp();

        $this->workDir = storage_path('framework/testing/import-'.uniqid());
        config([
            'lankaguide.import.cache_path' => $this->workDir.'/cache',
            'lankaguide.import.geojson_path' => $this->workDir.'/geo/lk-districts.geojson',
        ]);

        Http::preventStrayRequests();
        Sleep::fake();
        Storage::fake('public');

        $this->seed([LocationSeeder::class, CategorySeeder::class, PlaceSeeder::class]);
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->workDir);

        parent::tearDown();
    }

    protected function fixture(string $name): string
    {
        return file_get_contents(base_path('tests/Fixtures/import/'.$name));
    }

    /**
     * @return array<mixed>
     */
    protected function fixtureJson(string $name): array
    {
        return json_decode($this->fixture($name), true);
    }

    /**
     * Write the district shapes file the Overpass importers read, as lg:import-districts would.
     * Kandy is the square lat 7.1–7.5, lng 80.5–80.9.
     */
    protected function writeKandyGeometry(): void
    {
        File::ensureDirectoryExists(dirname(config('lankaguide.import.geojson_path')));
        File::put(config('lankaguide.import.geojson_path'), json_encode([
            'type' => 'FeatureCollection',
            'features' => [[
                'type' => 'Feature',
                'properties' => ['slug' => 'kandy', 'name' => 'Kandy'],
                'geometry' => Geo::roundGeometry([
                    'type' => 'Polygon',
                    'coordinates' => [[[80.5, 7.1], [80.9, 7.1], [80.9, 7.5], [80.5, 7.5], [80.5, 7.1]]],
                ]),
            ]],
        ]));
    }

    /**
     * A real JPEG so the WebP conversion runs for real.
     */
    protected function jpeg(int $width = 1920, int $height = 1280): string
    {
        $image = imagecreatetruecolor($width, $height);
        imagefilledrectangle($image, 0, 0, $width, $height, imagecolorallocate($image, 15, 118, 110));
        ob_start();
        imagejpeg($image, null, 70);

        return ob_get_clean();
    }
}
