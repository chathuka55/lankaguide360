<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('category_place', function (Blueprint $table) {
            $table->unsignedSmallInteger('category_id');
            $table->unsignedInteger('place_id');
            $table->primary(['category_id', 'place_id']);

            $table->foreign('category_id')->references('id')->on('categories')->cascadeOnDelete();
            $table->foreign('place_id')->references('id')->on('places')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('category_place');
    }
};
