<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('trip_travellers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('trip_id')->constrained()->cascadeOnDelete();
            $table->string('full_name', 120);
            $table->string('country', 80)->nullable();
            $table->unsignedTinyInteger('age')->nullable();
            $table->string('email', 190)->nullable();
            $table->string('phone', 30)->nullable();
            $table->string('whatsapp', 30)->nullable();
            $table->string('passport_no', 30)->nullable();
            $table->boolean('is_lead')->default(false);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('trip_travellers');
    }
};
