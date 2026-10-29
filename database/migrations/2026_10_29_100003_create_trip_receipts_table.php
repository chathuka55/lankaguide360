<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * E-receipts (booking vouchers). Issued by an agent once the traveller has accepted the booking
 * and the timings are final. The snapshot freezes what was confirmed, so later edits to the trip
 * never change an issued receipt; re-issuing keeps the number and replaces the snapshot.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('trip_receipts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('trip_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('number', 30)->unique();          // LG360-R-2026-00001
            $table->char('verification_code', 16);            // in the QR code link
            $table->foreignId('issued_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('issued_at');
            $table->unsignedSmallInteger('revision')->default(1);
            $table->char('currency', 3);                        // shown to the traveller (LKR)
            $table->decimal('exchange_rate', 12, 4);            // 1 USD in that currency, at issue
            $table->decimal('total_usd', 12, 2);
            $table->decimal('paid_usd', 12, 2)->default(0);
            $table->string('payment_method', 40)->nullable();
            $table->string('payment_note', 255)->nullable();
            $table->json('snapshot');
            $table->timestamps();
        });

        Schema::table('trips', function (Blueprint $table) {
            $table->timestamp('accepted_at')->nullable()->after('approved_at');
            $table->timestamp('itinerary_finalized_at')->nullable()->after('accepted_at');
        });
    }

    public function down(): void
    {
        Schema::table('trips', function (Blueprint $table) {
            $table->dropColumn(['accepted_at', 'itinerary_finalized_at']);
        });
        Schema::dropIfExists('trip_receipts');
    }
};
