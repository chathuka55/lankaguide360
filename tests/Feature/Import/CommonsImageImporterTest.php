<?php

namespace Tests\Feature\Import;

use App\Enums\MediaSource;
use App\Enums\MediaStatus;
use App\Models\Media;
use App\Models\Place;
use App\Services\Import\CommonsImageImporter;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\DataProvider;

class CommonsImageImporterTest extends ImportTestCase
{
    private function fakeCommons(): void
    {
        Http::fake([
            'commons.wikimedia.org/*' => Http::response($this->fixture('commons-search.json')),
            'upload.wikimedia.test/*' => Http::response($this->jpeg(), 200, ['Content-Type' => 'image/jpeg']),
        ]);
    }

    public function test_it_imports_only_freely_licensed_large_photos_as_webp_variants(): void
    {
        $this->fakeCommons();

        $report = app(CommonsImageImporter::class)->run(['place' => 'sigiriya-rock-fortress']);

        $place = Place::where('slug', 'sigiriya-rock-fortress')->sole();
        $media = $place->media()->get();

        // Accepted: CC BY-SA 3.0 and Public domain. Rejected: NC license, 800 px, location map, SVG.
        $this->assertSame(2, $report->created);
        $this->assertCount(2, $media);
        Http::assertNotSent(fn ($request) => str_contains($request->url(), 'nc.jpg') || str_contains($request->url(), 'map.png'));

        $first = $media->first();
        $this->assertSame(MediaSource::Commons, $first->source);
        $this->assertSame(MediaStatus::Draft, $first->status);
        $this->assertSame('Bernard Gagnon', $first->author, 'HTML stripped from the author');
        $this->assertSame('CC BY-SA 3.0', $first->license);
        $this->assertSame('https://creativecommons.org/licenses/by-sa/3.0', $first->license_url);
        $this->assertSame('https://commons.wikimedia.org/wiki/File:Sigiriya_02.jpg', $first->source_url);
        $this->assertSame('Lion paw on the Sigiriya rock, Sri Lanka', $first->alt);
        $this->assertTrue($first->is_cover, 'First image becomes the cover');
        $this->assertFalse($media->last()->is_cover);
        $this->assertSame('Own work by Jane Doe', $media->last()->author, 'Falls back to Credit when Artist is empty');

        $this->assertSame([400, 800, 1600], array_keys($first->variants));
        foreach ($first->variants as $width => $path) {
            Storage::disk('public')->assertExists($path);
            $this->assertStringEndsWith("-{$width}.webp", $path);
            $this->assertStringStartsWith("media/place/{$place->id}/", $path);
            $this->assertSame($width, getimagesizefromstring(Storage::disk('public')->get($path))[0]);
        }
        $this->assertSame(1600, $first->width);
        $this->assertSame(1067, $first->height);
    }

    public function test_very_long_commons_descriptions_fit_the_caption_column(): void
    {
        $data = $this->fixtureJson('commons-search.json');
        $data['query']['pages'][1]['imageinfo'][0]['extmetadata']['ImageDescription']['value'] = str_repeat('Sigiriya rock fortress. ', 60);
        Http::fake([
            'commons.wikimedia.org/*' => Http::response($data),
            'upload.wikimedia.test/*' => Http::response($this->jpeg(), 200, ['Content-Type' => 'image/jpeg']),
        ]);

        $report = app(CommonsImageImporter::class)->run(['place' => 'sigiriya-rock-fortress']);

        $this->assertSame(0, $report->failed);
        $caption = Place::where('slug', 'sigiriya-rock-fortress')->sole()->media()->first()->caption;
        $this->assertLessThanOrEqual(500, mb_strlen($caption));
    }

    public function test_rerunning_does_not_duplicate_photos_or_replace_the_cover(): void
    {
        $this->fakeCommons();
        app(CommonsImageImporter::class)->run(['place' => 'sigiriya-rock-fortress']);
        app(CommonsImageImporter::class)->run(['place' => 'sigiriya-rock-fortress']);

        $place = Place::where('slug', 'sigiriya-rock-fortress')->sole();
        $this->assertSame(2, $place->media()->count());
        $this->assertSame(1, $place->media()->where('is_cover', true)->count());
    }

    public function test_places_that_already_have_enough_photos_are_skipped(): void
    {
        $place = Place::where('slug', 'sigiriya-rock-fortress')->sole();
        Media::factory()->count(2)->for($place, 'mediable')->create();
        $this->fakeCommons();

        app(CommonsImageImporter::class)->run(['place' => 'sigiriya-rock-fortress', 'per_place' => 2]);

        Http::assertNothingSent();
    }

    public function test_limit_caps_the_number_of_places(): void
    {
        $this->fakeCommons();

        app(CommonsImageImporter::class)->run(['limit' => 2, 'per_place' => 1]);

        $this->assertSame(2, Media::distinct()->count('mediable_id'));
    }

    /**
     * @return array<string, array{string|null, bool}>
     */
    public static function licenses(): array
    {
        return [
            'CC0' => ['CC0', true],
            'CC0 1.0' => ['CC0 1.0', true],
            'CC BY 2.0' => ['CC BY 2.0', true],
            'CC BY-SA 4.0' => ['CC BY-SA 4.0', true],
            'ported' => ['CC BY-SA 3.0 de', true],
            'public domain' => ['Public domain', true],
            'PD-self' => ['PD-self', true],
            'non-commercial' => ['CC BY-NC-SA 2.0', false],
            'no-derivatives' => ['CC BY-ND 4.0', false],
            'GFDL' => ['GFDL', false],
            'all rights reserved' => ['All rights reserved', false],
            'missing' => [null, false],
        ];
    }

    #[DataProvider('licenses')]
    public function test_license_policy(?string $license, bool $accepted): void
    {
        $this->assertSame($accepted, CommonsImageImporter::acceptsLicense($license));
    }
}
