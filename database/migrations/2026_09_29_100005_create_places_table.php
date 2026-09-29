<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * SRS 7.3 places, plus the import columns added in phase 2 (status, source,
     * wikipedia_title, wikipedia_url, osm_id). lat/lng are nullable because seeded
     * places get their coordinates from the Wikipedia importer (phase 3).
     */
    public function up(): void
    {
        Schema::create('places', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedSmallInteger('district_id')->index();
            $table->string('name', 150);
            $table->string('slug', 170)->unique();
            $table->string('short_description', 300)->nullable();
            $table->text('description')->nullable();
            $table->decimal('lat', 9, 6)->nullable();
            $table->decimal('lng', 9, 6)->nullable();
            $table->unsignedSmallInteger('visit_minutes')->default(90);
            $table->time('open_time')->nullable();
            $table->time('close_time')->nullable();
            $table->enum('best_time_slot', ['any', 'sunrise', 'morning', 'afternoon', 'sunset'])->default('any');
            $table->decimal('fee_foreign_adult', 10, 2)->default(0);
            $table->decimal('fee_foreign_child', 10, 2)->default(0);
            $table->enum('crowd_level', ['low', 'medium', 'high'])->default('medium');
            $table->boolean('is_hidden_gem')->default(false);
            $table->string('best_months', 40)->nullable();
            $table->string('cover_image')->nullable();
            $table->boolean('is_active')->default(true);
            $table->enum('status', ['draft', 'published'])->default('draft')->index();
            $table->string('source', 20)->default('seed');
            $table->string('wikipedia_title', 200)->nullable();
            $table->string('wikipedia_url')->nullable();
            $table->string('osm_id', 30)->nullable()->unique();
            $table->timestamps();

            $table->foreign('district_id')->references('id')->on('districts');
            $table->fullText(['name', 'short_description']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('places');
    }
};
