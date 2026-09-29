<?php

namespace Tests\Feature\Admin;

use App\Enums\MediaStatus;
use App\Enums\PublishStatus;
use App\Models\Category;
use App\Models\District;
use App\Models\Media;
use App\Models\Place;
use App\Models\Trip;
use App\Models\TripDay;
use App\Models\TripStop;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class PlaceAdminTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed();
        $this->admin = User::factory()->admin()->create();
        Storage::fake('public');
    }

    /**
     * @return array<string, mixed>
     */
    private function validPlace(array $overrides = []): array
    {
        return [
            'name' => 'Secret Waterfall',
            'slug' => '',
            'district_id' => District::where('slug', 'badulla')->value('id'),
            'categories' => [Category::where('slug', 'hill-country')->value('id')],
            'short_description' => 'A quiet waterfall.',
            'description' => 'A quiet waterfall near Ella.',
            'lat' => '6.87',
            'lng' => '81.05',
            'visit_minutes' => 60,
            'open_time' => '08:00',
            'close_time' => '17:30',
            'best_time_slot' => 'morning',
            'fee_foreign_adult' => '5.50',
            'fee_foreign_child' => '0',
            'crowd_level' => 'low',
            'is_hidden_gem' => '1',
            'is_active' => '1',
            'status' => 'draft',
            ...$overrides,
        ];
    }

    public function test_admin_creates_a_place_with_categories(): void
    {
        $response = $this->actingAs($this->admin)->post(route('admin.places.store'), $this->validPlace());

        $place = Place::where('slug', 'secret-waterfall')->sole();
        $response->assertRedirect(route('admin.places.edit', $place));

        $this->assertSame('manual', $place->source);
        $this->assertSame('5.50', $place->fee_foreign_adult);
        $this->assertTrue($place->is_hidden_gem);
        $this->assertSame(PublishStatus::Draft, $place->status);
        $this->assertSame(['hill-country'], $place->categories->pluck('slug')->all());
        $this->assertSame('08:00:00', $place->open_time);
    }

    public function test_publishing_requires_coordinates_and_a_category(): void
    {
        $this->actingAs($this->admin)
            ->from(route('admin.places.create'))
            ->post(route('admin.places.store'), $this->validPlace(['status' => 'published', 'lat' => '', 'lng' => '', 'categories' => []]))
            ->assertRedirect(route('admin.places.create'))
            ->assertSessionHasErrors(['lat' => 'Set the map position before publishing.', 'categories']);

        $this->assertFalse(Place::where('slug', 'secret-waterfall')->exists());
    }

    public function test_coordinates_must_be_inside_sri_lanka(): void
    {
        $this->actingAs($this->admin)
            ->post(route('admin.places.store'), $this->validPlace(['lat' => '51.5', 'lng' => '-0.12']))
            ->assertSessionHasErrors(['lat', 'lng']);
    }

    public function test_admin_updates_and_publishes_a_place(): void
    {
        $place = Place::where('slug', 'sigiriya-rock-fortress')->sole();

        $this->actingAs($this->admin)
            ->put(route('admin.places.update', $place), $this->validPlace([
                'name' => 'Sigiriya Rock Fortress',
                'slug' => 'sigiriya-rock-fortress',
                'district_id' => $place->district_id,
                'status' => 'published',
                'fee_foreign_adult' => '36',
            ]))
            ->assertRedirect(route('admin.places.edit', $place))
            ->assertSessionHas('status', 'Place saved.');

        $place->refresh();
        $this->assertSame(PublishStatus::Published, $place->status);
        $this->assertSame('36.00', $place->fee_foreign_adult);
        $this->assertTrue(Place::published()->whereKey($place->id)->exists());
    }

    public function test_bulk_publish_with_photos_skips_places_that_are_not_ready(): void
    {
        $ready = Place::where('slug', 'sigiriya-rock-fortress')->sole();
        $ready->update(['lat' => 7.95, 'lng' => 80.75]);
        $photo = Media::factory()->for($ready, 'mediable')->create(['status' => 'draft']);
        $notReady = Place::where('slug', 'ella-rock')->sole(); // no coordinates

        $this->actingAs($this->admin)
            ->post(route('admin.places.bulk'), ['action' => 'publish_with_photos', 'ids' => [$ready->id, $notReady->id]])
            ->assertSessionHas('status', '1 place(s) updated.')
            ->assertSessionHas('warning', fn ($warning) => str_contains($warning, 'Ella Rock: no map coordinates'));

        $this->assertSame(PublishStatus::Published, $ready->fresh()->status);
        $this->assertSame(MediaStatus::Published, $photo->fresh()->status);
        $this->assertSame(PublishStatus::Draft, $notReady->fresh()->status);
    }

    public function test_reject_hides_a_place_and_restore_brings_it_back(): void
    {
        $place = Place::where('slug', 'ella-rock')->sole();

        $this->actingAs($this->admin)->post(route('admin.places.bulk'), ['action' => 'reject', 'ids' => [$place->id]]);
        $this->assertFalse($place->fresh()->is_active);
        $this->actingAs($this->admin)->get(route('admin.review.index'))->assertDontSee('ella-rock/edit');

        $this->actingAs($this->admin)->post(route('admin.places.bulk'), ['action' => 'restore', 'ids' => [$place->id]]);
        $this->assertTrue($place->fresh()->is_active);
    }

    public function test_bulk_requires_a_selection(): void
    {
        $this->actingAs($this->admin)
            ->post(route('admin.places.bulk'), ['action' => 'publish', 'ids' => []])
            ->assertSessionHasErrors(['ids' => 'Tick at least one row first.']);
    }

    public function test_delete_removes_the_place_and_its_photos(): void
    {
        $place = Place::where('slug', 'namunukula')->sole(); // not in any seeded package
        $photo = Media::factory()->for($place, 'mediable')->create();
        Storage::disk('public')->put($photo->variants[400], 'x');

        $this->actingAs($this->admin)->delete(route('admin.places.destroy', $place))->assertRedirect(route('admin.places.index'));

        $this->assertModelMissing($place);
        $this->assertModelMissing($photo);
        Storage::disk('public')->assertMissing($photo->variants[400]);
    }

    public function test_places_used_in_trips_cannot_be_deleted(): void
    {
        $place = Place::where('slug', 'ella-rock')->sole();
        $day = TripDay::factory()->for(Trip::factory())->create();
        TripStop::factory()->for($day, 'tripDay')->create(['place_id' => $place->id]);

        $this->actingAs($this->admin)->delete(route('admin.places.destroy', $place))->assertSessionHas('error');

        $this->assertModelExists($place);
    }

    public function test_index_filters_combine(): void
    {
        $this->actingAs($this->admin)
            ->get(route('admin.places.index', ['district' => District::where('slug', 'kandy')->value('id'), 'q' => 'Temple']))
            ->assertOk()
            ->assertSee('Temple of the Sacred Tooth Relic')
            ->assertDontSee('Sigiriya Rock Fortress');

        $this->actingAs($this->admin)
            ->get(route('admin.places.index', ['gems' => 1]))
            ->assertSee('Ritigala')
            ->assertDontSee('Galle Fort');
    }
}
