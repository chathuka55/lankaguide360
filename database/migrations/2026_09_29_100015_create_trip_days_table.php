<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('trip_days', function (Blueprint $table) {
            $table->id();
            $table->foreignId('trip_id')->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('day_number');
            $table->date('date');
            $table->string('title', 150)->nullable();
            $table->string('overnight_town', 80)->nullable();
            $table->unsignedInteger('hotel_id')->nullable();
            $table->unsignedInteger('room_rate_id')->nullable();
            $table->unsignedTinyInteger('rooms')->default(1);
            $table->decimal('drive_km', 7, 1)->default(0);
            $table->unsignedSmallInteger('drive_minutes')->default(0);

            $table->unique(['trip_id', 'day_number']);
            $table->foreign('hotel_id')->references('id')->on('hotels');
            $table->foreign('room_rate_id')->references('id')->on('room_rates');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('trip_days');
    }
};
