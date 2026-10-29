<?php

namespace Tests\Feature;

use App\Enums\PublishStatus;
use App\Enums\TripStatus;
use App\Enums\UserRole;
use App\Mail\TestMail;
use App\Models\Category;
use App\Models\Hotel;
use App\Models\Media;
use App\Models\Place;
use App\Models\Trip;
use App\Models\User;
use Database\Seeders\CategorySeeder;
use Database\Seeders\LocationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use OpenSpout\Reader\XLSX\Reader;
use Tests\TestCase;

class ExportsMailAndPublishingTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['role' => UserRole::Admin, 'email' => 'boss@example.test']);
    }

    /**
     * @return array<string, array<int, array<int, mixed>>> sheet name => rows of cell values
     */
    private function readXlsx(string $path): array
    {
        // No formula elements anywhere: text typed by travellers is stored as text.
        $zip = new \ZipArchive;
        $zip->open($path);
        for ($i = 0; $i < $zip->numFiles; $i++) {
            if (str_starts_with($zip->getNameIndex($i), 'xl/worksheets/')) {
                $this->assertStringNotContainsString('<f>', (string) $zip->getFromIndex($i), 'Exports never contain formulas');
            }
        }
        $zip->close();

        $reader = new Reader;
        $reader->open($path);
        $sheets = [];
        foreach ($reader->getSheetIterator() as $sheet) {
            foreach ($sheet->getRowIterator() as $row) {
                $sheets[$sheet->getName()][] = $row->toArray();
            }
        }
        $reader->close();

        return $sheets;
    }

    public function test_excel_export_of_one_report_and_the_full_workbook(): void
    {
        $admin = $this->admin();
        $trip = Trip::factory()->status(TripStatus::Confirmed)->create(['final_total' => '1500.00', 'submitted_at' => now()->subDay()]);
        $trip->travellers()->create(['full_name' => '=cmd|"/c calc"!A1', 'country' => '@SUM(1+1)', 'is_lead' => true]);

        $response = $this->actingAs($admin)->get(route('admin.reports.export', ['report' => 'trips', 'format' => 'xlsx']));
        $response->assertOk()->assertHeader('content-type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        $rows = $this->readXlsx($response->baseResponse->getFile()->getPathname())['All trip requests'];
        $this->assertSame('Reference', $rows[0][0]);
        $this->assertSame($trip->reference, $rows[1][0]);
        $this->assertContains('=cmd|"/c calc"!A1', $rows[1], 'Kept as plain text');
        $this->assertTrue(collect($rows[1])->contains(fn ($v) => (is_int($v) || is_float($v)) && (float) $v === 1500.0), 'Amounts are real numbers, not text');

        $workbook = $this->actingAs($admin)->get(route('admin.reports.workbook'));
        $workbook->assertOk();
        $sheets = $this->readXlsx($workbook->baseResponse->getFile()->getPathname());
        $this->assertSame('Summary', array_key_first($sheets));
        $this->assertCount(11, $sheets);
        $this->assertContains(['Trip requests', 1], $sheets['Summary']);

        $this->actingAs(User::factory()->create(['role' => UserRole::Agent]))->get(route('admin.reports.workbook'))->assertForbidden();
    }

    public function test_reports_page_links_to_excel_exports(): void
    {
        $this->actingAs($this->admin())->get(route('admin.reports.index'))
            ->assertOk()
            ->assertSee('Analytics workbook (Excel)')
            ->assertSee('format=xlsx', false);
    }

    public function test_mail_test_command_and_settings_button(): void
    {
        Mail::fake();
        $admin = $this->admin();

        $this->artisan('lg:mail-test', ['email' => 'someone@example.test'])->assertSuccessful();
        Mail::assertSent(TestMail::class, fn ($mail) => $mail->hasTo('someone@example.test'));

        $this->actingAs($admin)->post(route('admin.settings.test-email'), ['email' => 'boss@example.test'])
            ->assertRedirect(route('admin.settings.edit'))
            ->assertSessionHas('status');
        $this->actingAs($admin)->post(route('admin.settings.test-email'), ['email' => 'not-an-email'])->assertSessionHasErrors('email');
        $this->actingAs(User::factory()->create(['role' => UserRole::Agent]))->post(route('admin.settings.test-email'), ['email' => 'a@b.test'])->assertForbidden();

        config(['mail.default' => 'log']);
        $this->artisan('lg:mail-test', ['email' => 'someone@example.test'])->assertFailed()->expectsOutputToContain('MAIL_MAILER is "log"');
    }

    public function test_queue_is_processed_by_the_scheduler(): void
    {
        $this->artisan('schedule:list')->expectsOutputToContain('queue:work --stop-when-empty');
    }

    public function test_publish_ready_publishes_finished_places_with_photos_and_hotels(): void
    {
        $this->seed([LocationSeeder::class, CategorySeeder::class]);
        $category = Category::first();

        $described = Place::factory()->create(['description' => 'An old temple.', 'lat' => 7.1, 'lng' => 80.1]);
        $withPhoto = Place::factory()->create(['description' => null, 'lat' => 7.2, 'lng' => 80.2]);
        $suggestion = Place::factory()->create(['description' => null, 'lat' => 7.3, 'lng' => 80.3]);
        $noLocation = Place::factory()->create(['description' => 'Somewhere.', 'lat' => null, 'lng' => null]);
        $rejected = Place::factory()->create(['description' => 'Hidden.', 'lat' => 7.4, 'lng' => 80.4, 'is_active' => false]);
        foreach ([$described, $withPhoto, $suggestion, $noLocation, $rejected] as $place) {
            $place->categories()->attach($category);
        }
        $photo = Media::factory()->for($withPhoto, 'mediable')->create(['status' => 'draft']);
        $hotel = Hotel::factory()->create(['town' => 'Kandy', 'lat' => 7.29, 'lng' => 80.63]);
        $hiddenHotel = Hotel::factory()->create(['town' => 'Kandy', 'is_active' => false]);

        $this->artisan('lg:publish-ready --dry-run')->assertSuccessful();
        $this->assertSame(0, Place::published()->count());

        $this->artisan('lg:publish-ready')->assertSuccessful();

        $this->assertSame(PublishStatus::Published, $described->fresh()->status);
        $this->assertSame(PublishStatus::Published, $withPhoto->fresh()->status);
        $this->assertSame('published', $photo->fresh()->status->value);
        $this->assertSame(PublishStatus::Draft, $suggestion->fresh()->status, 'No description or photo: stays in the review queue');
        $this->assertSame(PublishStatus::Draft, $noLocation->fresh()->status);
        $this->assertSame(PublishStatus::Draft, $rejected->fresh()->status);
        $this->assertSame(PublishStatus::Published, $hotel->fresh()->status);
        $this->assertSame(PublishStatus::Draft, $hiddenHotel->fresh()->status);
    }
}
