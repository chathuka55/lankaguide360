<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('route_cache', function (Blueprint $table) {
            $table->id();
            $table->decimal('from_lat', 9, 6);
            $table->decimal('from_lng', 9, 6);
            $table->decimal('to_lat', 9, 6);
            $table->decimal('to_lng', 9, 6);
            $table->decimal('km', 7, 1);
            $table->unsignedSmallInteger('minutes');
            $table->mediumText('geometry')->nullable();
            $table->string('provider', 30)->nullable();
            $table->timestamp('updated_at')->nullable();

            $table->unique(['from_lat', 'from_lng', 'to_lat', 'to_lng'], 'leg');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('route_cache');
    }
};
