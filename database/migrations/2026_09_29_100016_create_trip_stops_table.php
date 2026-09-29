<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('trip_stops', function (Blueprint $table) {
            $table->id();
            $table->foreignId('trip_day_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('place_id');
            $table->unsignedTinyInteger('sequence');
            $table->time('arrive_at')->nullable();
            $table->time('depart_at')->nullable();
            $table->decimal('km_from_prev', 7, 1)->nullable();
            $table->unsignedSmallInteger('minutes_from_prev')->nullable();

            $table->foreign('place_id')->references('id')->on('places');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('trip_stops');
    }
};
