<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('packages', function (Blueprint $table) {
            $table->increments('id');
            $table->foreignId('template_trip_id')->constrained('trips');
            $table->string('name', 150);
            $table->string('slug', 170)->unique();
            $table->enum('tier', ['budget', 'premium', 'luxury'])->nullable();
            $table->unsignedTinyInteger('days')->nullable();
            $table->decimal('from_price', 10, 2)->nullable();
            $table->string('cover_image')->nullable();
            $table->string('summary', 300)->nullable();
            $table->text('inclusions')->nullable();
            $table->text('exclusions')->nullable();
            $table->boolean('is_featured')->default(false);
            $table->smallInteger('sort_order')->default(0);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('packages');
    }
};
