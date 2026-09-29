<?php

namespace Tests\Feature\Admin;

use App\Enums\PublishStatus;
use App\Models\Hotel;
use App\Models\ImportLog;
use App\Models\Media;
use App\Models\Place;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReviewQueueTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed();
        $this->admin = User::factory()->admin()->create();
    }

    public function test_places_tab_lists_drafts_with_checks_and_sources(): void
    {
        $place = Place::where('slug', 'sigiriya-rock-fortress')->sole();
        $place->update(['lat' => 7.95, 'lng' => 80.75, 'wikipedia_url' => 'https://en.wikipedia.org/wiki/Sigiriya', 'short_description' => 'Ancient rock fortress.']);

        $this->actingAs($this->admin)->get(route('admin.review.index', ['district' => $place->district_id]))
            ->assertOk()
            ->assertSee('Sigiriya Rock Fortress')
            ->assertSee('Ancient rock fortress.')
            ->assertSee('CC BY-SA')
            ->assertSee('✓ Coordinates')
            ->assertSee(number_format(Place::count()));

        $this->actingAs($this->admin)->get(route('admin.review.index', ['district' => $place->district_id, 'ready' => '1']))
            ->assertSee('Sigiriya Rock Fortress')
            ->assertDontSee('Pidurangala Rock');
    }

    public function test_places_needing_a_wikipedia_title_are_highlighted(): void
    {
        $place = Place::where('slug', 'ella-rock')->sole();
        ImportLog::create(['importer' => 'wikipedia', 'target_type' => 'place', 'target_id' => $place->id, 'status' => 'not_found', 'message' => 'No article titled "Ella Rock". Search suggests: "Ella, Sri Lanka".']);

        $fixed = Place::where('slug', 'gregory-lake')->sole();
        ImportLog::create(['importer' => 'wikipedia', 'target_type' => 'place', 'target_id' => $fixed->id, 'status' => 'needs_review', 'message' => 'disambiguation']);
        ImportLog::create(['importer' => 'wikipedia', 'target_type' => 'place', 'target_id' => $fixed->id, 'status' => 'updated', 'message' => 'Updated after the title was fixed']);

        $this->actingAs($this->admin)->get(route('admin.review.index'))
            ->assertSee('1 place need a correct Wikipedia title')
            ->assertSee('Search suggests: &quot;Ella, Sri Lanka&quot;', false)
            ->assertDontSee('disambiguation');
    }

    public function test_hotels_tab_lists_draft_hotels_and_filters(): void
    {
        Hotel::factory()->create(['name' => 'Draft Inn', 'osm_id' => 'node/5', 'tier' => 'budget']);
        Hotel::factory()->published()->create(['name' => 'Published Palace']);
        Hotel::factory()->create(['name' => 'Rejected Rest', 'is_active' => false]);

        $this->actingAs($this->admin)->get(route('admin.review.index', ['tab' => 'hotels']))
            ->assertOk()
            ->assertSee('Draft Inn')
            ->assertSee('openstreetmap.org/node/5', false)
            ->assertDontSee('Published Palace')
            ->assertDontSee('Rejected Rest');

        $this->actingAs($this->admin)->get(route('admin.review.index', ['tab' => 'hotels', 'tier' => 'luxury']))
            ->assertDontSee('Draft Inn');
    }

    public function test_photos_tab_shows_draft_photos_with_credit(): void
    {
        $place = Place::where('slug', 'galle-fort')->sole();
        Media::factory()->for($place, 'mediable')->create(['author' => 'Jane Photographer', 'status' => 'draft']);
        Media::factory()->for($place, 'mediable')->create(['author' => 'Already Published', 'status' => 'published']);

        $this->actingAs($this->admin)->get(route('admin.review.index', ['tab' => 'photos']))
            ->assertOk()
            ->assertSee('Galle Fort')
            ->assertSee('Photo: Jane Photographer, CC BY-SA 4.0, via Wikimedia Commons')
            ->assertDontSee('Already Published');
    }

    public function test_publishing_from_the_queue_removes_the_item(): void
    {
        $place = Place::where('slug', 'galle-fort')->sole();
        $place->update(['lat' => 6.03, 'lng' => 80.22]);

        $this->actingAs($this->admin)
            ->from(route('admin.review.index'))
            ->post(route('admin.places.bulk'), ['action' => 'publish_with_photos', 'ids' => [$place->id]])
            ->assertRedirect(route('admin.review.index'));

        $this->assertSame(PublishStatus::Published, $place->fresh()->status);
        $this->actingAs($this->admin)->get(route('admin.review.index'))->assertDontSee('galle-fort/edit');
    }
}
