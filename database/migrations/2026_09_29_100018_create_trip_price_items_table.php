<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('trip_price_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('trip_id')->constrained()->cascadeOnDelete();
            $table->enum('category', ['accommodation', 'transport', 'guide', 'meals', 'tickets', 'activities', 'service_fee', 'tax', 'discount']);
            $table->string('description', 200)->nullable();
            $table->decimal('qty', 8, 2)->default(1);
            $table->decimal('unit_price', 10, 2)->nullable();
            $table->decimal('amount', 12, 2);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('trip_price_items');
    }
};
