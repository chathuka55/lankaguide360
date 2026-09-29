<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * SRS 7.3 room_rates, plus is_estimate to flag placeholder rate bands (BUILD-PROMPTS 3.2).
     */
    public function up(): void
    {
        Schema::create('room_rates', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('hotel_id');
            $table->string('room_type', 60);
            $table->enum('meal_plan', ['RO', 'BB', 'HB', 'FB', 'AI'])->default('BB');
            $table->decimal('price_per_night', 10, 2);
            $table->unsignedTinyInteger('max_occupancy')->default(2);
            $table->decimal('extra_bed_price', 10, 2)->default(0);
            $table->date('season_from')->nullable();
            $table->date('season_to')->nullable();
            $table->boolean('is_estimate')->default(false);

            $table->foreign('hotel_id')->references('id')->on('hotels')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('room_rates');
    }
};
