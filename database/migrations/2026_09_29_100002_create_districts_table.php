<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('districts', function (Blueprint $table) {
            $table->smallIncrements('id');
            $table->unsignedSmallInteger('province_id');
            $table->string('name', 60);
            $table->string('slug', 80)->unique();
            $table->decimal('lat', 9, 6)->nullable();
            $table->decimal('lng', 9, 6)->nullable();
            $table->text('description')->nullable();
            $table->string('image')->nullable();

            $table->foreign('province_id')->references('id')->on('provinces');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('districts');
    }
};
