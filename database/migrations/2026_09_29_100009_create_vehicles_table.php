<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vehicles', function (Blueprint $table) {
            $table->smallIncrements('id');
            $table->string('type', 60);
            $table->string('example_model', 80)->nullable();
            $table->enum('tier', ['budget', 'premium', 'luxury']);
            $table->unsignedTinyInteger('min_pax');
            $table->unsignedTinyInteger('max_pax');
            $table->unsignedTinyInteger('luggage_capacity')->nullable();
            $table->decimal('day_rate', 10, 2);
            $table->decimal('km_rate', 8, 2)->default(0);
            $table->string('image')->nullable();

            $table->index(['tier', 'min_pax', 'max_pax']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vehicles');
    }
};
