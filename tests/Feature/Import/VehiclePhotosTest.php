<?php

namespace Tests\Feature\Import;

use App\Models\Vehicle;
use Database\Seeders\TravelOptionSeeder;
use Illuminate\Support\Facades\Http;

class VehiclePhotosTest extends ImportTestCase
{
    public function test_each_vehicle_type_gets_its_commons_photo_once(): void
    {
        $this->seed(TravelOptionSeeder::class);
        $title = (require database_path('seeders/data/vehicle_photos.php'))['Car'];

        Http::fake([
            'commons.wikimedia.org/*' => Http::response(['query' => ['pages' => [[
                'title' => $title,
                'imageinfo' => [[
                    'width' => 2554, 'height' => 1912, 'mime' => 'image/jpeg',
                    'thumburl' => 'https://upload.wikimedia.test/thumb/axio.jpg',
                    'descriptionurl' => 'https://commons.wikimedia.org/wiki/File:Axio.JPG',
                    'extmetadata' => [
                        'Artist' => ['value' => '<a href="//commons.wikimedia.org/wiki/User:Someone">Someone</a>'],
                        'LicenseShortName' => ['value' => 'CC0'],
                        'LicenseUrl' => ['value' => 'https://creativecommons.org/publicdomain/zero/1.0/'],
                        'ImageDescription' => ['value' => 'Toyota Corolla Axio'],
                    ],
                ]],
            ]]]]),
            'upload.wikimedia.test/*' => Http::response($this->jpeg(), 200, ['Content-Type' => 'image/jpeg']),
        ]);

        $this->artisan('lg:vehicle-photos')->expectsOutputToContain('Car: CC0, Someone')->assertSuccessful();

        $car = Vehicle::where('type', 'Car')->sole();
        $this->assertSame('CC0', $car->cover->license);
        $this->assertSame('Someone', $car->cover->author);
        $this->assertSame('https://commons.wikimedia.org/wiki/File:Axio.JPG', $car->cover->source_url);

        // A second run never adds a second photo (or replaces an admin's).
        $this->artisan('lg:vehicle-photos')->assertSuccessful();
        $this->assertSame(1, $car->allMedia()->count());
    }
}
