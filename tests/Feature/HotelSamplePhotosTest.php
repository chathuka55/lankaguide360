<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\District;
use App\Models\Hotel;
use App\Models\Media;
use App\Models\SamplePhoto;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Hotels without their own photos show labelled sample photos that suit their setting, tier and type.
 */
class HotelSamplePhotosTest extends TestCase
{
    use RefreshDatabase;

    private District $beach;

    protected function setUp(): void
    {
        parent::setUp();

        $this->beach = District::factory()->create(['name' => 'Galle', 'slug' => 'galle']);
        $this->beach->categories()->attach(Category::factory()->create(['slug' => 'beach-coastal']));

        foreach ([
            ['Hostel dorm room', 'room', null, ['hostel'], null],
            ['Guest house room', 'room', ['budget'], ['guest_house', 'hotel'], null],
            ['Suite with a sea view', 'room', ['luxury'], ['hotel', 'resort'], ['beach']],
            ['Misty hill-country view', 'view', null, null, ['hill']],
            ['Beach with cabanas', 'view', null, null, ['beach']],
            ['Pool at sunset', 'pool', ['premium', 'luxury'], null, null],
            ['Rice and curry', 'food', null, null, null],
        ] as [$name, $subject, $tiers, $types, $settings]) {
            $photo = SamplePhoto::create(['name' => $name, 'subject' => $subject, 'tiers' => $tiers, 'types' => $types, 'settings' => $settings, 'commons_title' => "File:{$name}.jpg"]);
            Media::factory()->cover()->create(['mediable_type' => 'sample_photo', 'mediable_id' => $photo->id, 'alt' => $name, 'status' => 'published', 'author' => "Author of {$name}"]);
        }
    }

    public function test_photos_suit_the_hotel(): void
    {
        $hostel = Hotel::factory()->published()->for($this->beach)->create(['type' => 'hostel', 'tier' => 'budget']);
        $luxury = Hotel::factory()->published()->for($this->beach)->create(['type' => 'resort', 'tier' => 'luxury']);

        // Cards use one of the hotel's own sample set, never the food photo.
        $this->assertContains($hostel->sampleCover()->alt, ['Hostel dorm room', 'Beach with cabanas']);
        $this->assertSame(['Hostel dorm room', 'Beach with cabanas', 'Rice and curry'], $hostel->samplePhotos()->pluck('alt')->all());
        $this->assertSame(['Suite with a sea view', 'Beach with cabanas', 'Pool at sunset', 'Rice and curry'], $luxury->samplePhotos()->pluck('alt')->all());
    }

    public function test_hotel_page_labels_sample_photos_and_credits_them(): void
    {
        $hotel = Hotel::factory()->published()->for($this->beach)->create(['type' => 'guest_house', 'tier' => 'budget', 'name' => 'Sea Breeze Guest House']);

        $this->get($hotel->url())->assertOk()
            ->assertSee('Sample photo')
            ->assertSee('They are not photos of Sea Breeze Guest House')
            ->assertSee('Author of Guest house room')
            ->assertDontSee('Photos of this hotel are coming soon');
    }

    public function test_a_hotel_with_its_own_photos_never_shows_samples(): void
    {
        $hotel = Hotel::factory()->published()->for($this->beach)->create(['type' => 'hotel', 'tier' => 'budget']);
        Media::factory()->cover()->create(['mediable_type' => 'hotel', 'mediable_id' => $hotel->id, 'status' => 'published', 'alt' => 'Our own lobby']);

        $this->get($hotel->url())->assertOk()->assertSee('Our own lobby')->assertDontSee('Sample photo');
    }

    public function test_district_page_lists_hotels_with_photos_and_links(): void
    {
        $hotel = Hotel::factory()->published()->for($this->beach)->create(['type' => 'guest_house', 'tier' => 'budget', 'name' => 'Lagoon Rest']);

        $this->get(route('districts.show', $this->beach))->assertOk()
            ->assertSee('Lagoon Rest')->assertSee($hotel->url(), false)->assertSee('Sample photo');
    }
}
