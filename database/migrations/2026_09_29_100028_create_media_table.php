<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Photos for places, hotels and other models (replaces SRS place_images).
     * Every row records where the image came from and its license (CLAUDE.md data rules).
     */
    public function up(): void
    {
        Schema::create('media', function (Blueprint $table) {
            $table->id();
            $table->morphs('mediable');
            $table->string('path');
            $table->json('variants')->nullable(); // {"400": "...webp", "800": "...", "1600": "..."}
            $table->unsignedSmallInteger('width')->nullable();
            $table->unsignedSmallInteger('height')->nullable();
            $table->string('alt')->nullable();
            $table->string('caption', 500)->nullable();
            $table->enum('source', ['commons', 'upload', 'url']);
            $table->string('source_url', 500)->nullable();
            $table->string('author')->nullable();
            $table->string('license', 100)->nullable();
            $table->string('license_url', 500)->nullable();
            $table->boolean('is_cover')->default(false);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->enum('status', ['draft', 'published'])->default('draft')->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('media');
    }
};
