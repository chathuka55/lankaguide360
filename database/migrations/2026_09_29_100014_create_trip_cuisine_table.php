<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('trip_cuisine', function (Blueprint $table) {
            $table->foreignId('trip_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('cuisine_id');
            $table->primary(['trip_id', 'cuisine_id']);

            $table->foreign('cuisine_id')->references('id')->on('cuisines');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('trip_cuisine');
    }
};
