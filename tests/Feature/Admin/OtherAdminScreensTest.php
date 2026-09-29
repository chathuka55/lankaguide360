<?php

namespace Tests\Feature\Admin;

use App\Enums\UserRole;
use App\Models\Category;
use App\Models\ContactMessage;
use App\Models\Cuisine;
use App\Models\District;
use App\Models\Guide;
use App\Models\Package;
use App\Models\Review;
use App\Models\Setting;
use App\Models\Trip;
use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class OtherAdminScreensTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed();
        $this->admin = User::where('email', 'admin@lankaguide360.test')->sole();
    }

    public function test_vehicle_crud(): void
    {
        $this->actingAs($this->admin)->post(route('admin.vehicles.store'), [
            'type' => 'Tuk-tuk', 'example_model' => 'Bajaj RE', 'tier' => 'budget',
            'min_pax' => 1, 'max_pax' => 2, 'luggage_capacity' => 1, 'day_rate' => '20', 'km_rate' => '0.25',
        ])->assertRedirect(route('admin.vehicles.index'));

        $vehicle = Vehicle::where('type', 'Tuk-tuk')->sole();
        $this->actingAs($this->admin)->put(route('admin.vehicles.update', $vehicle), [...$vehicle->toArray(), 'tier' => 'budget', 'day_rate' => '22'])->assertSessionHasNoErrors();
        $this->assertSame('22.00', $vehicle->fresh()->day_rate);

        $this->actingAs($this->admin)->delete(route('admin.vehicles.destroy', $vehicle));
        $this->assertModelMissing($vehicle);
    }

    public function test_vehicle_max_must_not_be_below_min(): void
    {
        $this->actingAs($this->admin)->post(route('admin.vehicles.store'), [
            'type' => 'Van', 'tier' => 'budget', 'min_pax' => 6, 'max_pax' => 4, 'day_rate' => '1', 'km_rate' => '0',
        ])->assertSessionHasErrors('max_pax');
    }

    public function test_guide_languages_are_normalised(): void
    {
        $this->actingAs($this->admin)->post(route('admin.guides.store'), [
            'name' => 'National guide (Japanese)', 'type' => 'national', 'languages' => ['japanese', 'english', 'english'], 'day_rate' => '70', 'is_available' => '1',
        ])->assertRedirect(route('admin.guides.index'));

        $this->assertSame(['Japanese', 'English'], Guide::where('name', 'National guide (Japanese)')->sole()->languages);
    }

    public function test_cuisines_can_be_added_renamed_and_deleted(): void
    {
        $this->actingAs($this->admin)->post(route('admin.cuisines.store'), ['name' => 'Thai'])->assertSessionHas('status');
        $thai = Cuisine::where('name', 'Thai')->sole();

        $this->actingAs($this->admin)->put(route('admin.cuisines.update', $thai), ['name' => 'Sri Lankan'])->assertSessionHasErrors('name');
        $this->actingAs($this->admin)->put(route('admin.cuisines.update', $thai), ['name' => 'Thai & Asian']);
        $this->assertSame('Thai & Asian', $thai->fresh()->name);

        $this->actingAs($this->admin)->delete(route('admin.cuisines.destroy', $thai));
        $this->assertModelMissing($thai);
    }

    public function test_category_with_places_cannot_be_deleted(): void
    {
        $beach = Category::where('slug', 'beach-coastal')->sole();

        $this->actingAs($this->admin)->delete(route('admin.categories.destroy', $beach))->assertSessionHas('error');
        $this->assertModelExists($beach);

        $this->actingAs($this->admin)->post(route('admin.categories.store'), ['name' => 'Wellness & Ayurveda', 'districts' => [District::first()->id]])
            ->assertRedirect(route('admin.categories.index'));
        $this->assertSame(1, Category::where('slug', 'wellness-ayurveda')->sole()->districts()->count());
    }

    public function test_district_categories_and_centre_can_be_edited(): void
    {
        $colombo = District::where('slug', 'colombo')->sole();
        $beach = Category::where('slug', 'beach-coastal')->value('id');

        $this->actingAs($this->admin)->put(route('admin.districts.update', $colombo), [
            'description' => 'The commercial capital.', 'lat' => '6.9', 'lng' => '79.9', 'categories' => [$beach],
        ])->assertRedirect(route('admin.districts.edit', $colombo));

        $this->assertSame(['beach-coastal'], $colombo->fresh()->categories->pluck('slug')->all());
    }

    public function test_reviews_can_be_added_and_approved(): void
    {
        $this->actingAs($this->admin)->post(route('admin.reviews.store'), [
            'author_name' => 'Anna', 'country' => 'Germany', 'rating' => 5, 'comment' => 'Wonderful trip!', 'is_approved' => '0',
        ])->assertRedirect(route('admin.reviews.index'));

        $review = Review::sole();
        $this->assertFalse($review->is_approved);

        $this->actingAs($this->admin)->patch(route('admin.reviews.approve', $review));
        $this->assertTrue($review->fresh()->is_approved);
        $this->assertSame(1, Review::approved()->count());
    }

    public function test_opening_a_contact_message_marks_it_read(): void
    {
        $message = ContactMessage::create(['name' => 'Ravi', 'email' => 'ravi@example.com', 'subject' => 'Family trip', 'body' => 'Hello']);

        $this->actingAs($this->admin)->get(route('admin.messages.show', $message))->assertOk()->assertSee('Family trip');

        $this->assertTrue($message->fresh()->is_read);
    }

    public function test_admin_creates_an_agent_account(): void
    {
        $this->actingAs($this->admin)->post(route('admin.users.store'), [
            'name' => 'New Agent', 'email' => 'new.agent@lankaguide360.test', 'role' => 'agent',
            'password' => 'Secret-pass-123', 'password_confirmation' => 'Secret-pass-123',
        ])->assertRedirect(route('admin.users.index'));

        $agent = User::where('email', 'new.agent@lankaguide360.test')->sole();
        $this->assertSame(UserRole::Agent, $agent->role);
        $this->assertTrue(Hash::check('Secret-pass-123', $agent->password));
        $this->assertNotNull($agent->email_verified_at);
    }

    public function test_admins_cannot_demote_or_delete_themselves(): void
    {
        $this->actingAs($this->admin)->put(route('admin.users.update', $this->admin), [
            'name' => 'Site Admin', 'email' => $this->admin->email, 'role' => 'traveller', 'password' => '', 'password_confirmation' => '',
        ])->assertSessionHasNoErrors();

        $this->assertSame(UserRole::Admin, $this->admin->fresh()->role);

        $this->actingAs($this->admin)->delete(route('admin.users.destroy', $this->admin))->assertForbidden();
    }

    public function test_admin_can_change_another_users_role(): void
    {
        $traveller = User::factory()->create();

        $this->actingAs($this->admin)->put(route('admin.users.update', $traveller), [
            'name' => $traveller->name, 'email' => $traveller->email, 'role' => 'agent', 'password' => '', 'password_confirmation' => '',
        ]);

        $this->assertSame(UserRole::Agent, $traveller->fresh()->role);
    }

    public function test_settings_are_validated_and_saved(): void
    {
        $settings = Setting::pluck('value', 'key')->all();

        $this->actingAs($this->admin)->put(route('admin.settings.update'), ['settings' => [...$settings, 'tax_percent' => '150']])
            ->assertSessionHasErrors('settings.tax_percent');

        $this->actingAs($this->admin)->put(route('admin.settings.update'), ['settings' => [...$settings, 'tax_percent' => '18', 'currency' => 'usd']])
            ->assertRedirect(route('admin.settings.edit'));

        $this->assertSame('18', Setting::get('tax_percent'));
        $this->assertSame('USD', Setting::get('currency'));
    }

    public function test_packages_need_a_template_trip(): void
    {
        // The seeded packages' template trips are offered as templates.
        $this->actingAs($this->admin)->get(route('admin.packages.create'))->assertOk()->assertSee('PKG-');

        $trip = Trip::factory()->create(['days' => 5]);
        $this->actingAs($this->admin)->post(route('admin.packages.store'), [
            'template_trip_id' => $trip->id, 'name' => '5-day Cultural Triangle', 'tier' => 'premium', 'days' => 5, 'from_price' => '650', 'is_featured' => '1',
        ])->assertRedirect(route('admin.packages.index'));

        $package = Package::where('slug', '5-day-cultural-triangle')->sole();
        $this->assertTrue($package->is_featured);
        $this->assertTrue($package->templateTrip->is($trip));
    }
}
