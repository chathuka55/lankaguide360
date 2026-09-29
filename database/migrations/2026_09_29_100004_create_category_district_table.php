<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('category_district', function (Blueprint $table) {
            $table->unsignedSmallInteger('category_id');
            $table->unsignedSmallInteger('district_id');
            $table->primary(['category_id', 'district_id']);

            $table->foreign('category_id')->references('id')->on('categories')->cascadeOnDelete();
            $table->foreign('district_id')->references('id')->on('districts')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('category_district');
    }
};
