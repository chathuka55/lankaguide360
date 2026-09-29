<?php

namespace Tests\Feature\Admin;

use App\Enums\PublishStatus;
use App\Models\District;
use App\Models\Hotel;
use App\Models\Place;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class AdminAccessTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed();
    }

    /**
     * @return array<string, array{string}>
     */
    public static function masterDataPages(): array
    {
        return [
            'places' => ['/admin/places'],
            'districts' => ['/admin/districts'],
            'categories' => ['/admin/categories'],
            'hotels' => ['/admin/hotels'],
            'vehicles' => ['/admin/vehicles'],
            'guides' => ['/admin/guides'],
            'cuisines' => ['/admin/cuisines'],
            'packages' => ['/admin/packages'],
        ];
    }

    /**
     * @return array<string, array{string}>
     */
    public static function adminOnlyPages(): array
    {
        return [
            'review queue' => ['/admin/review'],
            'review queue hotels' => ['/admin/review?tab=hotels'],
            'review queue photos' => ['/admin/review?tab=photos'],
            'reviews' => ['/admin/reviews'],
            'messages' => ['/admin/messages'],
            'users' => ['/admin/users'],
            'settings' => ['/admin/settings'],
            'import tools' => ['/admin/imports'],
            'create place' => ['/admin/places/create'],
            'create hotel' => ['/admin/hotels/create'],
            'create vehicle' => ['/admin/vehicles/create'],
            'create user' => ['/admin/users/create'],
        ];
    }

    #[DataProvider('masterDataPages')]
    public function test_admin_and_agent_can_open_master_data_lists(string $uri): void
    {
        $this->actingAs(User::factory()->admin()->create())->get($uri)->assertOk();
        $this->actingAs(User::factory()->agent()->create())->get($uri)->assertOk();
    }

    #[DataProvider('adminOnlyPages')]
    public function test_admin_only_pages(string $uri): void
    {
        $this->actingAs(User::factory()->admin()->create())->get($uri)->assertOk();
        $this->actingAs(User::factory()->agent()->create())->get($uri)->assertForbidden();
    }

    #[DataProvider('masterDataPages')]
    public function test_travellers_are_forbidden(string $uri): void
    {
        $this->actingAs(User::factory()->create())->get($uri)->assertForbidden();
    }

    public function test_agent_sees_edit_forms_read_only_and_cannot_save(): void
    {
        $agent = User::factory()->agent()->create();
        $place = Place::where('slug', 'sigiriya-rock-fortress')->sole();

        $this->actingAs($agent)->get(route('admin.places.edit', $place))
            ->assertOk()
            ->assertSee('Read-only')
            ->assertDontSee('Save place');

        $this->actingAs($agent)->put(route('admin.places.update', $place), ['name' => 'Hacked'])->assertForbidden();
        $this->actingAs($agent)->post(route('admin.places.bulk'), ['action' => 'publish', 'ids' => [$place->id]])->assertForbidden();
        $this->actingAs($agent)->delete(route('admin.places.destroy', $place))->assertForbidden();
        $this->actingAs($agent)->post(route('admin.media.upload', ['type' => 'place', 'id' => $place->id]), [
            'photo' => UploadedFile::fake()->image('p.jpg', 1200, 800),
        ])->assertForbidden();

        $this->assertSame('Sigiriya Rock Fortress', $place->fresh()->name);
    }

    public function test_agent_cannot_change_hotels_or_districts(): void
    {
        $agent = User::factory()->agent()->create();
        $hotel = Hotel::factory()->create();

        $this->actingAs($agent)->get(route('admin.hotels.edit', $hotel))->assertOk()->assertSee('Read-only');
        $this->actingAs($agent)->put(route('admin.hotels.update', $hotel), ['name' => 'X'])->assertForbidden();
        $this->actingAs($agent)->post(route('admin.hotels.rates.store', $hotel), ['room_type' => 'X'])->assertForbidden();
        $this->actingAs($agent)->put(route('admin.districts.update', District::first()), ['lat' => 7, 'lng' => 80])->assertForbidden();
    }

    public function test_agent_menu_hides_admin_sections(): void
    {
        $this->actingAs(User::factory()->agent()->create())->get('/admin')
            ->assertOk()
            ->assertSee('Places')
            ->assertDontSee('Review queue')
            ->assertDontSee('Users')
            ->assertDontSee('Settings');
    }

    public function test_dashboard_shows_review_counts_to_admins(): void
    {
        Place::where('slug', 'sigiriya-rock-fortress')->update(['status' => PublishStatus::Published, 'lat' => 7.95, 'lng' => 80.75]);

        $this->actingAs(User::factory()->admin()->create())->get('/admin')
            ->assertOk()
            ->assertSee('Waiting for review')
            ->assertSee('Draft places')
            ->assertSee('1 published');
    }
}
