<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('trip_category', function (Blueprint $table) {
            $table->foreignId('trip_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('category_id');
            $table->primary(['trip_id', 'category_id']);

            $table->foreign('category_id')->references('id')->on('categories');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('trip_category');
    }
};
