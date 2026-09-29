<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Future live tracking (SRS 8.9, FR-28).
     */
    public function up(): void
    {
        Schema::create('trip_locations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('trip_id')->constrained()->cascadeOnDelete();
            $table->decimal('lat', 9, 6);
            $table->decimal('lng', 9, 6);
            $table->smallInteger('accuracy_m')->nullable();
            $table->enum('source', ['traveller', 'driver']);
            $table->timestamp('recorded_at')->nullable();

            $table->index(['trip_id', 'recorded_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('trip_locations');
    }
};
