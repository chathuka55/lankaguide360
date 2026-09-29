<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * SRS 7.3 hotels, plus the import columns added in phase 2 (status, wikipedia_title,
     * osm_id, website, phone, address), a slug, the OSM tourism type and timestamps.
     */
    public function up(): void
    {
        Schema::create('hotels', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedSmallInteger('district_id');
            $table->string('town', 80);
            $table->string('name', 150);
            $table->string('slug', 170)->unique();
            $table->string('type', 30)->nullable();
            $table->enum('tier', ['budget', 'premium', 'luxury']);
            $table->unsignedTinyInteger('star_rating')->nullable();
            $table->boolean('kid_friendly')->default(false);
            $table->decimal('lat', 9, 6)->nullable();
            $table->decimal('lng', 9, 6)->nullable();
            $table->json('amenities')->nullable();
            $table->string('cover_image')->nullable();
            $table->boolean('is_active')->default(true);
            $table->enum('status', ['draft', 'published'])->default('draft')->index();
            $table->string('wikipedia_title', 200)->nullable();
            $table->string('osm_id', 30)->nullable()->unique();
            $table->string('website')->nullable();
            $table->string('phone', 60)->nullable();
            $table->string('address')->nullable();
            $table->timestamps();

            $table->foreign('district_id')->references('id')->on('districts');
            $table->index(['town', 'tier']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hotels');
    }
};
