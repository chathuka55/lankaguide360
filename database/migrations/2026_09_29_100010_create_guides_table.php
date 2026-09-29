<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('guides', function (Blueprint $table) {
            $table->smallIncrements('id');
            $table->string('name', 120);
            $table->enum('type', ['chauffeur', 'national', 'site']);
            $table->json('languages')->nullable();
            $table->decimal('day_rate', 10, 2);
            $table->string('phone', 30)->nullable();
            $table->boolean('is_available')->default(true);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('guides');
    }
};
