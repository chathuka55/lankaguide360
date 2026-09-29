<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Phase 10: agent-only notes and the reason when the final price differs from the estimate.
     * Phase 9: dietary notes, luggage and the traveller's consent for storing personal data (NFR-10).
     */
    public function up(): void
    {
        Schema::table('trips', function (Blueprint $table) {
            $table->text('internal_notes')->nullable()->after('special_requests');
            $table->string('price_note', 500)->nullable()->after('final_total');
            $table->text('dietary_notes')->nullable()->after('meal_plan');
            $table->unsignedTinyInteger('luggage')->nullable()->after('infants');
            $table->timestamp('data_consent_at')->nullable()->after('tracking_consent');
            $table->timestamp('completed_at')->nullable()->after('approved_at');
        });
    }

    public function down(): void
    {
        Schema::table('trips', function (Blueprint $table) {
            $table->dropColumn(['internal_notes', 'price_note', 'dietary_notes', 'luggage', 'data_consent_at', 'completed_at']);
        });
    }
};
