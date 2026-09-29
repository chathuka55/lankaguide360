<?php

namespace Tests\Feature\Import;

use App\Jobs\RunImport;
use App\Models\ImportLog;
use App\Models\Place;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;

class ImportCommandsTest extends ImportTestCase
{
    public function test_import_places_command_runs_and_writes_logs(): void
    {
        Http::fake(['en.wikipedia.org/*' => Http::response($this->fixture('wikipedia-summary-sigiriya.json'))]);

        $this->artisan('lg:import-places', ['--only' => 'sigiriya-rock-fortress'])
            ->expectsOutputToContain('Updated')
            ->assertSuccessful();

        $this->assertNotNull(Place::where('slug', 'sigiriya-rock-fortress')->value('lat'));
        $this->assertTrue(ImportLog::where('importer', 'wikipedia')->where('status', 'info')->exists(), 'Run summary logged');
    }

    public function test_import_districts_command(): void
    {
        Http::fake([
            'www.geoboundaries.org/*' => Http::response($this->fixture('geoboundaries-api.json')),
            'github.com/*' => Http::response($this->fixture('lka-adm2.geojson')),
        ]);

        $this->artisan('lg:import-districts')->assertSuccessful();

        $this->assertFileExists(config('lankaguide.import.geojson_path'));
    }

    public function test_hotels_command_fails_clearly_without_district_shapes(): void
    {
        Http::fake();

        $this->artisan('lg:import-hotels', ['--district' => 'kandy'])->assertFailed();

        Http::assertNothingSent();
    }

    public function test_queue_option_dispatches_a_job_instead_of_running(): void
    {
        Queue::fake();
        Http::fake();

        $this->artisan('lg:import-images', ['--place' => 'sigiriya-rock-fortress', '--limit' => 3, '--queue' => true])->assertSuccessful();

        Http::assertNothingSent();
        Queue::assertPushed(RunImport::class, fn (RunImport $job) => $job->importer === 'images'
            && $job->options === ['place' => 'sigiriya-rock-fortress', 'limit' => 3, 'fresh' => false]);
    }

    public function test_import_all_queues_every_importer_in_order(): void
    {
        Bus::fake();

        $this->artisan('lg:import-all', ['--queue' => true])->assertSuccessful();

        Bus::assertChained([
            fn (RunImport $job) => $job->importer === 'districts',
            fn (RunImport $job) => $job->importer === 'places',
            fn (RunImport $job) => $job->importer === 'images',
            fn (RunImport $job) => $job->importer === 'hotels',
            fn (RunImport $job) => $job->importer === 'suggest-places',
        ]);
    }

    public function test_the_queued_job_runs_the_importer(): void
    {
        Http::fake(['en.wikipedia.org/*' => Http::response($this->fixture('wikipedia-summary-sigiriya.json'))]);

        (new RunImport('places', ['only' => 'sigiriya-rock-fortress']))->handle();

        $this->assertNotNull(Place::where('slug', 'sigiriya-rock-fortress')->value('lat'));
    }
}
