<?php

namespace Tests\Feature\Import;

use App\Enums\PublishStatus;
use App\Models\ImportLog;
use App\Models\Place;
use App\Services\Import\WikipediaImporter;
use Illuminate\Support\Facades\Http;

class WikipediaImporterTest extends ImportTestCase
{
    public function test_it_fills_description_coordinates_and_link(): void
    {
        Http::fake(['en.wikipedia.org/api/rest_v1/page/summary/Sigiriya' => Http::response($this->fixture('wikipedia-summary-sigiriya.json'))]);

        $report = app(WikipediaImporter::class)->run(['only' => 'sigiriya-rock-fortress']);

        $place = Place::where('slug', 'sigiriya-rock-fortress')->sole();
        $this->assertSame(1, $report->updated);
        $this->assertEqualsWithDelta(7.956944, (float) $place->lat, 0.000001);
        $this->assertEqualsWithDelta(80.759722, (float) $place->lng, 0.000001);
        $this->assertSame('https://en.wikipedia.org/wiki/Sigiriya', $place->wikipedia_url);
        $this->assertStringStartsWith('Sigiriya or Sinhagiri is an ancient rock fortress', $place->short_description);
        $this->assertStringEndsWith('(590 ft) high.', $place->short_description, 'Two sentences only');
        $this->assertStringContainsString('Cūlavaṃsa', $place->description);
        $this->assertSame(PublishStatus::Draft, $place->status, 'Imports never publish');

        Http::assertSent(fn ($request) => $request->hasHeader('User-Agent', config('lankaguide.user_agent')));
        $this->assertTrue(ImportLog::where('importer', 'wikipedia')->where('status', 'updated')->where('target_id', $place->id)->exists());
    }

    public function test_a_missing_title_is_logged_with_search_suggestions_and_not_guessed(): void
    {
        Http::fake([
            'en.wikipedia.org/api/rest_v1/*' => Http::response(['type' => 'not_found'], 404),
            'en.wikipedia.org/w/api.php*' => Http::response($this->fixture('wikipedia-search.json')),
        ]);

        $report = app(WikipediaImporter::class)->run(['only' => 'ella-rock']);

        $place = Place::where('slug', 'ella-rock')->sole();
        $this->assertSame(1, $report->skipped);
        $this->assertNull($place->lat);
        $this->assertSame('Ella Rock', $place->wikipedia_title, 'The title is not changed automatically');

        $log = ImportLog::where('importer', 'wikipedia')->where('status', 'not_found')->sole();
        $this->assertStringContainsString('"Ella, Sri Lanka"', $log->message);
        $this->assertSame($place->id, $log->target_id);
    }

    public function test_published_places_only_get_empty_fields_filled(): void
    {
        $place = Place::where('slug', 'sigiriya-rock-fortress')->sole();
        $place->update(['status' => PublishStatus::Published, 'short_description' => 'Edited by an admin.']);
        Http::fake(['en.wikipedia.org/*' => Http::response($this->fixture('wikipedia-summary-sigiriya.json'))]);

        app(WikipediaImporter::class)->run(['only' => 'sigiriya-rock-fortress']);

        $place->refresh();
        $this->assertSame('Edited by an admin.', $place->short_description);
        $this->assertNotNull($place->description);
        $this->assertNotNull($place->lat);
    }

    public function test_coordinates_outside_sri_lanka_are_ignored(): void
    {
        $data = $this->fixtureJson('wikipedia-summary-sigiriya.json');
        $data['coordinates'] = ['lat' => 51.5, 'lon' => -0.12];
        Http::fake(['en.wikipedia.org/*' => Http::response($data)]);

        app(WikipediaImporter::class)->run(['only' => 'sigiriya-rock-fortress']);

        $this->assertNull(Place::where('slug', 'sigiriya-rock-fortress')->value('lat'));
        $this->assertStringContainsString('outside Sri Lanka', ImportLog::where('status', 'updated')->value('message'));
    }

    public function test_responses_are_cached_so_reruns_do_not_download_again(): void
    {
        Http::fake(['en.wikipedia.org/*' => Http::response($this->fixture('wikipedia-summary-sigiriya.json'))]);

        app(WikipediaImporter::class)->run(['only' => 'sigiriya-rock-fortress']);
        $second = app(WikipediaImporter::class)->run(['only' => 'sigiriya-rock-fortress']);

        Http::assertSentCount(1);
        $this->assertSame(1, $second->skipped, 'Nothing changed on the second run');

        app(WikipediaImporter::class)->run(['only' => 'sigiriya-rock-fortress', 'fresh' => true]);
        Http::assertSentCount(2);
    }

    public function test_server_errors_are_retried_then_logged_as_failed(): void
    {
        Http::fake(['en.wikipedia.org/*' => Http::response('busy', 503)]);

        $report = app(WikipediaImporter::class)->run(['only' => 'sigiriya-rock-fortress']);

        Http::assertSentCount(3);
        $this->assertSame(1, $report->failed);
        $this->assertTrue(ImportLog::where('status', 'failed')->where('message', 'like', '%HTTP 503%')->exists());
    }
}
