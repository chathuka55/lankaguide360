<?php

namespace Tests\Feature\Admin;

use App\Enums\MediaSource;
use App\Enums\MediaStatus;
use App\Models\Media;
use App\Models\Place;
use App\Models\User;
use App\Services\Import\CommonsImageImporter;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\Feature\Import\ImportTestCase;

class MediaManagerTest extends ImportTestCase
{
    private User $admin;

    private Place $place;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->admin()->create();
        $this->place = Place::where('slug', 'sigiriya-rock-fortress')->sole();
    }

    /**
     * @return array<string, mixed>
     */
    private function rights(array $overrides = []): array
    {
        return [
            'alt' => 'Sigiriya at sunrise',
            'author' => 'Partner Hotel Ltd',
            'license' => 'Partner photo, used with written permission',
            'license_url' => '',
            'caption' => '',
            'rights' => '1',
            ...$overrides,
        ];
    }

    public function test_admin_uploads_a_photo_with_owner_and_license(): void
    {
        $this->actingAs($this->admin)
            ->from(route('admin.places.edit', $this->place))
            ->post(route('admin.media.upload', ['type' => 'place', 'id' => $this->place->id]), [
                'photo' => UploadedFile::fake()->image('sunrise.jpg', 2000, 1333),
                ...$this->rights(),
            ])
            ->assertSessionHas('status', 'Photo uploaded and published.');

        $media = $this->place->media()->sole();
        $this->assertSame(MediaSource::Upload, $media->source);
        $this->assertSame(MediaStatus::Published, $media->status);
        $this->assertSame('Partner Hotel Ltd', $media->author);
        $this->assertSame('Partner photo, used with written permission', $media->license);
        $this->assertTrue($media->is_cover);
        foreach ($media->variants as $path) {
            Storage::disk('public')->assertExists($path);
        }
    }

    public function test_upload_requires_owner_license_rights_and_a_large_enough_photo(): void
    {
        $this->actingAs($this->admin)
            ->post(route('admin.media.upload', ['type' => 'place', 'id' => $this->place->id]), [
                'photo' => UploadedFile::fake()->image('tiny.jpg', 400, 300),
                ...$this->rights(['author' => '', 'rights' => '']),
            ])
            ->assertSessionHasErrors(['photo', 'author', 'rights']);

        $this->assertSame(0, Media::count());
    }

    public function test_import_from_url_downloads_through_the_backend(): void
    {
        Http::fake(['93.184.216.34/*' => Http::response($this->jpeg(), 200, ['Content-Type' => 'image/jpeg'])]);

        $this->actingAs($this->admin)
            ->post(route('admin.media.url', ['type' => 'place', 'id' => $this->place->id]), [
                'url' => 'http://93.184.216.34/photo.jpg',
                ...$this->rights(),
            ])
            ->assertSessionHas('status');

        $media = $this->place->media()->sole();
        $this->assertSame(MediaSource::Url, $media->source);
        $this->assertSame('http://93.184.216.34/photo.jpg', $media->source_url);
        Http::assertSent(fn ($request) => $request->hasHeader('User-Agent', config('lankaguide.user_agent')));
    }

    public function test_import_from_url_refuses_private_addresses(): void
    {
        $this->actingAs($this->admin)
            ->post(route('admin.media.url', ['type' => 'place', 'id' => $this->place->id]), [
                'url' => 'http://127.0.0.1/admin.jpg',
                ...$this->rights(),
            ])
            ->assertSessionHas('error', 'Images from local or private network addresses are not allowed.');

        Http::assertNothingSent();
        $this->assertSame(0, Media::count());
    }

    public function test_import_from_url_requires_the_rights_checkbox(): void
    {
        $this->actingAs($this->admin)
            ->post(route('admin.media.url', ['type' => 'place', 'id' => $this->place->id]), [
                'url' => 'http://93.184.216.34/photo.jpg',
                ...$this->rights(['rights' => '']),
            ])
            ->assertSessionHasErrors(['rights' => 'Confirm that you have the right to use this image.']);
    }

    public function test_commons_search_returns_results_with_usability(): void
    {
        Http::fake(['commons.wikimedia.org/*' => Http::response($this->fixture('commons-search.json'))]);

        $response = $this->actingAs($this->admin)->getJson(route('admin.media.commons-search', ['q' => 'Sigiriya']))->assertOk();

        $results = collect($response->json('results'))->keyBy('title');
        $this->assertTrue($results['File:Sigiriya 02.jpg']['usable']);
        $this->assertSame('Bernard Gagnon', $results['File:Sigiriya 02.jpg']['author']);
        $this->assertFalse($results['File:Sigiriya non-commercial.jpg']['usable']);
        $this->assertSame('License not allowed', $results['File:Sigiriya non-commercial.jpg']['reason']);
        $this->assertSame('Looks like a map, logo or diagram', $results['File:Sigiriya location map.png']['reason']);
    }

    public function test_commons_import_of_selected_files(): void
    {
        Http::fake([
            'commons.wikimedia.org/*' => Http::response($this->fixture('commons-search.json')),
            'upload.wikimedia.test/*' => Http::response($this->jpeg(), 200, ['Content-Type' => 'image/jpeg']),
        ]);

        $this->actingAs($this->admin)
            ->post(route('admin.media.commons', ['type' => 'place', 'id' => $this->place->id]), [
                'titles' => ['File:Sigiriya 02.jpg', 'File:Sigiriya non-commercial.jpg'],
            ])
            ->assertSessionHas('status', '1 photo(s) imported from Wikimedia Commons with their credits.')
            ->assertSessionHas('warning', fn ($w) => str_contains($w, 'non-commercial.jpg (license)'));

        $media = $this->place->media()->sole();
        $this->assertSame('CC BY-SA 3.0', $media->license);
        $this->assertSame(MediaStatus::Published, $media->status, 'Chosen by an admin, so published');
    }

    public function test_rejected_commons_photos_are_never_imported_again(): void
    {
        Http::fake([
            'commons.wikimedia.org/*' => Http::response($this->fixture('commons-search.json')),
            'upload.wikimedia.test/*' => Http::response($this->jpeg(), 200, ['Content-Type' => 'image/jpeg']),
        ]);
        app(CommonsImageImporter::class)->run(['place' => $this->place->slug]);
        $rejected = $this->place->media()->where('source_url', 'https://commons.wikimedia.org/wiki/File:Sigiriya_02.jpg')->sole();
        $paths = $rejected->variants;

        $this->actingAs($this->admin)->delete(route('admin.media.destroy', $rejected))->assertSessionHas('status');

        $rejected->refresh();
        $this->assertSame(MediaStatus::Rejected, $rejected->status);
        foreach ($paths as $path) {
            Storage::disk('public')->assertMissing($path);
        }
        $this->assertTrue($this->place->media()->sole()->is_cover, 'The remaining photo became the cover');

        app(CommonsImageImporter::class)->run(['place' => $this->place->slug, 'fresh' => true]);

        $this->assertSame(1, $this->place->allMedia()->where('source_url', 'https://commons.wikimedia.org/wiki/File:Sigiriya_02.jpg')->count());
    }

    public function test_cover_reorder_publish_and_alt_text(): void
    {
        [$first, $second] = Media::factory()->count(2)->for($this->place, 'mediable')->sequence(['sort_order' => 1, 'is_cover' => true], ['sort_order' => 2])->create();

        $this->actingAs($this->admin)->post(route('admin.media.cover', $second));
        $this->assertTrue($second->fresh()->is_cover);
        $this->assertFalse($first->fresh()->is_cover);

        $this->actingAs($this->admin)
            ->postJson(route('admin.media.reorder', ['type' => 'place', 'id' => $this->place->id]), ['ids' => [$second->id, $first->id]])
            ->assertOk();
        $this->assertSame([$second->id, $first->id], $this->place->media()->pluck('id')->all());

        $this->actingAs($this->admin)->patch(route('admin.media.update', $first), ['alt' => 'Lion staircase', 'caption' => '', 'status' => 'published']);
        $this->assertSame('Lion staircase', $first->fresh()->alt);
        $this->assertSame(MediaStatus::Published, $first->fresh()->status);

        $this->actingAs($this->admin)->post(route('admin.media.bulk'), ['action' => 'publish', 'ids' => [$second->id]]);
        $this->assertSame(MediaStatus::Published, $second->fresh()->status);
    }

    public function test_unknown_owner_types_are_not_found(): void
    {
        $this->actingAs($this->admin)
            ->post(route('admin.media.upload', ['type' => 'user', 'id' => $this->admin->id]), [
                'photo' => UploadedFile::fake()->image('x.jpg', 1200, 800), ...$this->rights(),
            ])
            ->assertNotFound();
    }
}
