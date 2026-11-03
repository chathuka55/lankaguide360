<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Freely licensed sample photos (rooms, views, pools, food) shown on hotels that have no photos
 * of their own, always labelled "Sample photo". The image itself is a media row owned by the
 * sample photo, like every other imported image. Rows come from data/hotel_sample_photos.php.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sample_photos', function (Blueprint $table) {
            $table->smallIncrements('id');
            $table->string('name', 120);
            $table->string('subject', 20);          // room | view | pool | food
            $table->json('tiers')->nullable();      // budget/premium/luxury; null = any
            $table->json('types')->nullable();      // hotel types (hostel, guest_house, …); null = any
            $table->json('settings')->nullable();   // beach | hill | inland; null = any
            $table->string('commons_title')->unique();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sample_photos');
    }
};
