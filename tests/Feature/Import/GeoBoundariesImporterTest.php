<?php

namespace Tests\Feature\Import;

use App\Models\District;
use App\Models\ImportLog;
use App\Services\Import\DistrictGeometry;
use App\Services\Import\GeoBoundariesImporter;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;

class GeoBoundariesImporterTest extends ImportTestCase
{
    private function fakeGeoBoundaries(): void
    {
        Http::fake([
            'www.geoboundaries.org/*' => Http::response($this->fixture('geoboundaries-api.json')),
            'github.com/*' => Http::response($this->fixture('lka-adm2.geojson')),
        ]);
    }

    public function test_it_saves_district_shapes_and_updates_centroids(): void
    {
        $this->fakeGeoBoundaries();

        $report = app(GeoBoundariesImporter::class)->run();

        $this->assertSame(2, $report->updated);
        $this->assertSame(1, $report->failed, 'Atlantis District has no match');

        $saved = json_decode(File::get(config('lankaguide.import.geojson_path')), true);
        $this->assertSame(['kandy', 'monaragala'], array_column(array_column($saved['features'], 'properties'), 'slug'));
        $this->assertStringContainsString('geoBoundaries', $saved['attribution']);

        $kandy = District::where('slug', 'kandy')->sole();
        $this->assertEqualsWithDelta(7.3, (float) $kandy->lat, 0.0001);
        $this->assertEqualsWithDelta(80.7, (float) $kandy->lng, 0.0001);

        // "Moneragala District" is a spelling variant of Monaragala.
        $monaragala = District::where('slug', 'monaragala')->sole();
        $this->assertEqualsWithDelta(6.75, (float) $monaragala->lat, 0.0001);

        $this->assertTrue(ImportLog::where('importer', 'geoboundaries')->where('status', 'failed')->where('message', 'like', '%Atlantis%')->exists());
        $this->assertTrue(ImportLog::where('importer', 'geoboundaries')->where('status', 'not_found')->where('target_id', District::where('slug', 'galle')->value('id'))->exists());
    }

    public function test_the_saved_file_is_readable_by_district_geometry(): void
    {
        $this->fakeGeoBoundaries();
        app(GeoBoundariesImporter::class)->run();

        $geometry = app(DistrictGeometry::class);
        $kandy = District::where('slug', 'kandy')->sole();

        $this->assertTrue($geometry->contains($kandy, 7.29, 80.63));
        $this->assertFalse($geometry->contains($kandy, 6.9, 79.9));
        $this->assertSame(['south' => 7.1, 'west' => 80.5, 'north' => 7.5, 'east' => 80.9], $geometry->bbox($kandy));
    }

    public function test_it_uses_the_simplified_file_when_the_full_file_is_too_large(): void
    {
        config(['lankaguide.import.geojson_max_bytes' => 100]);
        Http::fake([
            'www.geoboundaries.org/*' => Http::response($this->fixture('geoboundaries-api.json')),
            'github.com/*_simplified.geojson' => Http::response($this->fixture('lka-adm2.geojson')),
            'github.com/*' => Http::response(str_repeat(' ', 200).$this->fixture('lka-adm2.geojson')),
        ]);

        $report = app(GeoBoundariesImporter::class)->run();

        Http::assertSent(fn ($request) => str_ends_with($request->url(), '_simplified.geojson'));
        $this->assertSame(2, $report->updated);
    }

    public function test_district_names_match_spelling_variants(): void
    {
        $slugs = District::pluck('slug')->all();

        $this->assertSame('nuwara-eliya', DistrictGeometry::matchSlug('Nuwara Eliya District', $slugs));
        $this->assertSame('monaragala', DistrictGeometry::matchSlug('Moneragala District', $slugs));
        $this->assertSame('mullaitivu', DistrictGeometry::matchSlug('Mulativu', $slugs));
        $this->assertNull(DistrictGeometry::matchSlug('Atlantis District', $slugs));
    }
}
