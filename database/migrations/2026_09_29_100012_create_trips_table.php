<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('trips', function (Blueprint $table) {
            $table->id();
            $table->string('reference', 20)->unique(); // LG360-2026-00042
            $table->foreignId('user_id')->nullable()->index()->constrained('users')->nullOnDelete();
            $table->foreignId('agent_id')->nullable()->constrained('users')->nullOnDelete();
            $table->enum('tier', ['budget', 'premium', 'luxury']);
            $table->date('start_date');
            $table->unsignedTinyInteger('days');
            $table->unsignedTinyInteger('adults')->default(1);
            $table->unsignedTinyInteger('children')->default(0);
            $table->unsignedTinyInteger('infants')->default(0);
            $table->string('children_ages', 40)->nullable();
            $table->string('arrival_point', 80)->default('BIA Katunayake');
            $table->string('departure_point', 80)->default('BIA Katunayake');
            $table->enum('meal_plan', ['RO', 'BB', 'HB', 'FB', 'AI'])->default('BB');
            $table->unsignedSmallInteger('vehicle_id')->nullable();
            $table->enum('guide_type', ['chauffeur', 'national', 'site', 'none'])->default('chauffeur');
            $table->unsignedSmallInteger('guide_id')->nullable();
            $table->string('guide_language', 30)->default('English');
            $table->text('special_requests')->nullable();
            $table->enum('status', ['draft', 'submitted', 'under_review', 'approved', 'rejected', 'confirmed', 'in_progress', 'completed', 'cancelled'])
                ->default('draft')->index();
            $table->decimal('estimated_total', 12, 2)->nullable();
            $table->decimal('final_total', 12, 2)->nullable();
            $table->char('currency', 3)->default('USD');
            $table->char('access_token', 40); // guest view link
            $table->boolean('tracking_consent')->default(false);
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->timestamps();

            $table->foreign('vehicle_id')->references('id')->on('vehicles');
            $table->foreign('guide_id')->references('id')->on('guides');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('trips');
    }
};
