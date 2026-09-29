<?php

namespace Tests\Feature;

use App\Enums\PublishStatus;
use App\Http\Controllers\TripBuilderController;
use App\Mail\ContactMessageReceived;
use App\Models\Category;
use App\Models\ContactMessage;
use App\Models\District;
use App\Models\Hotel;
use App\Models\Media;
use App\Models\NewsletterSubscriber;
use App\Models\Place;
use App\Models\Review;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class PublicPagesTest extends TestCase
{
    use RefreshDatabase;

    private Place $sigiriya;

    private Place $dambulla;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed();

        $this->sigiriya = $this->publish('sigiriya-rock-fortress', 7.956944, 80.759722, 'Ancient rock fortress with lion paws.');
        $this->dambulla = $this->publish('dambulla-cave-temple', 7.8567, 80.6492, 'Cave temple complex.');
    }

    private function publish(string $slug, float $lat, float $lng, string $summary): Place
    {
        $place = Place::where('slug', $slug)->sole();
        $place->forceFill(['status' => PublishStatus::Published, 'lat' => $lat, 'lng' => $lng, 'short_description' => $summary, 'description' => $summary])->save();

        return $place;
    }

    public function test_home_shows_published_places_approved_reviews_and_stats(): void
    {
        Media::factory()->for($this->sigiriya, 'mediable')->cover()->create(['status' => 'published', 'author' => 'Hero Photographer']);
        Review::forceCreate(['author_name' => 'Anna', 'rating' => 5, 'comment' => 'Loved every day!', 'is_approved' => true]);
        Review::forceCreate(['author_name' => 'Spam', 'rating' => 1, 'comment' => 'Not approved yet', 'is_approved' => false]);

        $this->get('/')
            ->assertOk()
            ->assertSee('Plan your perfect Sri Lanka journey')
            ->assertSee('Sigiriya Rock Fortress')
            ->assertSee('Hero Photographer')
            ->assertSee('Loved every day!')
            ->assertDontSee('Not approved yet')
            ->assertDontSee('Hikkaduwa Beach')
            ->assertSee('Explore by category')
            ->assertSee('og:image', false);
    }

    public function test_destinations_list_only_shows_published_places_and_filters_combine(): void
    {
        $this->get(route('destinations.index'))
            ->assertOk()
            ->assertSee('Sigiriya Rock Fortress')
            ->assertSee('Dambulla Cave Temple')
            ->assertDontSee('Galle Fort')
            ->assertSee('2 places');

        $this->get(route('destinations.index', ['category' => 'hiking-adventure', 'district' => 'matale']))
            ->assertSee('Sigiriya Rock Fortress')
            ->assertDontSee('Dambulla Cave Temple');

        $this->get(route('destinations.index', ['province' => 'southern']))->assertSee('0 places');
        $this->get(route('destinations.index', ['q' => 'cave']))->assertSee('Dambulla Cave Temple')->assertDontSee('Sigiriya Rock Fortress');
        $this->get(route('destinations.index', ['gems' => 1]))->assertSee('0 places');
    }

    public function test_map_view_embeds_marker_data_for_published_places(): void
    {
        $this->get(route('destinations.index', ['view' => 'map']))
            ->assertOk()
            ->assertSee('placesMap(', false)
            ->assertSee('7.956944', false)
            ->assertSee('sigiriya-rock-fortress', false)
            ->assertDontSee('galle-fort', false);
    }

    public function test_place_page_shows_facts_credits_nearby_places_and_hotels(): void
    {
        $this->sigiriya->update(['fee_foreign_adult' => 36, 'wikipedia_url' => 'https://en.wikipedia.org/wiki/Sigiriya']);
        Media::factory()->for($this->sigiriya, 'mediable')->cover()->create(['status' => 'published', 'author' => 'Bernard Gagnon', 'license' => 'CC BY-SA 3.0']);
        Media::factory()->for($this->sigiriya, 'mediable')->create(['status' => 'draft', 'author' => 'Draft Author']);
        Hotel::factory()->published()->create(['name' => 'Rock View Lodge', 'tier' => 'budget', 'lat' => 7.95, 'lng' => 80.75]);
        Hotel::factory()->create(['name' => 'Draft Hotel', 'lat' => 7.95, 'lng' => 80.75]);

        $this->get($this->sigiriya->url())
            ->assertOk()
            ->assertSee('Sigiriya Rock Fortress')
            ->assertSee('$36.00')
            ->assertSee('Bernard Gagnon')
            ->assertDontSee('Draft Author')
            ->assertSee('CC BY-SA 4.0') // Wikipedia text attribution
            ->assertSee('Nearby places')
            ->assertSee('Dambulla Cave Temple')
            ->assertSee('Rock View Lodge')
            ->assertDontSee('Draft Hotel')
            ->assertSee('Add to my trip');
    }

    public function test_draft_places_and_wrong_districts_are_not_found(): void
    {
        $galleFort = Place::where('slug', 'galle-fort')->sole();

        $this->get(route('destinations.show', [$galleFort->district, $galleFort]))->assertNotFound();
        $this->get('/destinations/galle/sigiriya-rock-fortress')->assertNotFound();
    }

    public function test_add_to_my_trip_toggles_the_place_in_the_session(): void
    {
        $this->postJson(route('plan.places.toggle', $this->sigiriya))
            ->assertOk()
            ->assertJson(['added' => true, 'count' => 1])
            ->assertSessionHas(TripBuilderController::PICKED_PLACES, [$this->sigiriya->id]);

        $this->postJson(route('plan.places.toggle', $this->sigiriya))->assertJson(['added' => false, 'count' => 0]);

        $galleFort = Place::where('slug', 'galle-fort')->sole();
        $this->postJson(route('plan.places.toggle', $galleFort))->assertNotFound();
    }

    public function test_district_page_groups_published_places_by_category(): void
    {
        $this->get(route('districts.show', District::where('slug', 'matale')->sole()))
            ->assertOk()
            ->assertSee('Matale District')
            ->assertSee('Historical &amp; Cultural', false)
            ->assertSee('Sigiriya Rock Fortress')
            ->assertSee('districtMap(', false)
            ->assertDontSee('Nalanda Gedige');
    }

    public function test_contact_form_stores_and_emails_the_message(): void
    {
        Mail::fake();

        $this->post(route('contact.store'), [
            'name' => 'Ravi', 'email' => 'ravi@example.com', 'subject' => 'Family trip', 'body' => 'We are 4 people in December.',
        ])->assertRedirect(route('contact'))->assertSessionHas('status');

        $message = ContactMessage::sole();
        $this->assertSame('Family trip', $message->subject);
        Mail::assertQueued(ContactMessageReceived::class, fn ($mail) => $mail->contactMessage->is($message) && $mail->hasTo(config('lankaguide.contact.admin_email')));
    }

    public function test_contact_honeypot_and_validation(): void
    {
        Mail::fake();

        $this->post(route('contact.store'), ['name' => 'Bot', 'email' => 'bot@example.com', 'body' => 'Buy cheap things now!!', 'website' => 'http://spam.test'])
            ->assertSessionHas('status');
        $this->assertSame(0, ContactMessage::count());
        Mail::assertNothingQueued();

        $this->post(route('contact.store'), ['name' => '', 'email' => 'not-an-email', 'body' => 'short'])
            ->assertSessionHasErrors(['name', 'email', 'body']);
    }

    public function test_contact_page_lists_faqs(): void
    {
        $this->get(route('contact'))->assertOk()->assertSee('Do I need a visa to visit Sri Lanka?')->assertSee('pinMap(', false);
    }

    public function test_newsletter_signup_is_stored_once(): void
    {
        $this->postJson(route('newsletter.store'), ['email' => 'Anna@Example.com'])->assertOk()->assertJsonStructure(['message']);
        $this->postJson(route('newsletter.store'), ['email' => 'anna@example.com'])->assertOk();
        $this->postJson(route('newsletter.store'), ['email' => 'nope'])->assertUnprocessable();

        $this->assertSame(['anna@example.com'], NewsletterSubscriber::pluck('email')->all());
    }

    public function test_cached_lists_survive_a_serializing_cache_store(): void
    {
        // The array store used in tests never serializes; the database store used on the site does.
        config(['cache.default' => 'file', 'cache.stores.file.path' => storage_path('framework/testing/cache-'.uniqid())]);

        $this->get('/')->assertOk();
        $this->get('/')->assertOk()->assertSee('Beach &amp; Coastal', false);
        $this->get(route('destinations.index'))->assertOk()->assertSee('Central Province');
    }

    public function test_pages_have_unique_titles_and_meta_descriptions(): void
    {
        $this->get($this->sigiriya->url())
            ->assertSee('<title>Sigiriya Rock Fortress, Matale · LankaGuide360</title>', false)
            ->assertSee('<meta name="description" content="Ancient rock fortress with lion paws.">', false)
            ->assertSee('<link rel="canonical"', false);

        $category = Category::where('slug', 'beach-coastal')->sole();
        $this->get(route('destinations.index', ['category' => $category->slug]))->assertSee('<title>Beach &amp; Coastal in Sri Lanka · LankaGuide360</title>', false);
    }
}
