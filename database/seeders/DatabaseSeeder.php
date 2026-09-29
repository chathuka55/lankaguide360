<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database. Every seeder is idempotent: re-running
     * `php artisan db:seed` adds missing rows and never overwrites admin edits.
     */
    public function run(): void
    {
        $this->call([
            UserSeeder::class,
            LocationSeeder::class,
            CategorySeeder::class,
            PlaceSeeder::class,
            TravelOptionSeeder::class,
            FaqSeeder::class,
            PackageSeeder::class,
        ]);
    }
}
