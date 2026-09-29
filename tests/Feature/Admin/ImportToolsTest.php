<?php

namespace Tests\Feature\Admin;

use App\Jobs\RunImport;
use App\Models\ImportLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class ImportToolsTest extends TestCase
{
    use RefreshDatabase;

    public function test_page_lists_importers_with_last_run_and_logs(): void
    {
        ImportLog::create(['importer' => 'wikipedia', 'status' => 'info', 'message' => 'wikipedia: 106 created, 0 updated, 10 skipped, 0 failed']);
        ImportLog::create(['importer' => 'commons', 'status' => 'failed', 'message' => 'Download failed for a photo']);

        $this->actingAs(User::factory()->admin()->create())->get(route('admin.imports.index'))
            ->assertOk()
            ->assertSee('Place descriptions')
            ->assertSee('lg:import-hotels')
            ->assertSee('106 created, 0 updated, 10 skipped, 0 failed')
            ->assertSee('Download failed for a photo')
            ->assertSee('Never run.');
    }

    public function test_running_an_importer_queues_a_job(): void
    {
        Queue::fake();

        $this->actingAs(User::factory()->admin()->create())
            ->post(route('admin.imports.run'), ['importer' => 'images', 'limit' => 20, 'fresh' => '1'])
            ->assertSessionHas('status');

        Queue::assertPushed(RunImport::class, fn (RunImport $job) => $job->importer === 'images'
            && $job->options === ['fresh' => true, 'limit' => 20]);
    }

    public function test_limit_only_applies_to_images(): void
    {
        Queue::fake();

        $this->actingAs(User::factory()->admin()->create())->post(route('admin.imports.run'), ['importer' => 'places', 'limit' => 5]);

        Queue::assertPushed(RunImport::class, fn (RunImport $job) => $job->options === ['fresh' => false]);
    }

    public function test_unknown_importers_are_rejected(): void
    {
        Queue::fake();

        $this->actingAs(User::factory()->admin()->create())
            ->post(route('admin.imports.run'), ['importer' => 'google-images'])
            ->assertSessionHasErrors('importer');

        Queue::assertNothingPushed();
    }

    public function test_run_all_chains_every_importer(): void
    {
        Bus::fake();

        $this->actingAs(User::factory()->admin()->create())->post(route('admin.imports.run-all'));

        Bus::assertChained([RunImport::class, RunImport::class, RunImport::class, RunImport::class, RunImport::class]);
    }

    public function test_agents_cannot_run_imports(): void
    {
        Queue::fake();

        $this->actingAs(User::factory()->agent()->create())->post(route('admin.imports.run'), ['importer' => 'places'])->assertForbidden();

        Queue::assertNothingPushed();
    }
}
