<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * User-feedback round (Nov 2026): traveller type and NIC, local entry fees, local/foreign
 * pricing on trips, vehicle services and hire details, hotel descriptions.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('nationality', 80)->nullable()->after('country');
            $table->string('traveller_type', 10)->nullable()->after('nationality'); // local | foreign
            $table->string('nic', 12)->nullable()->after('traveller_type');           // Sri Lankan NIC, locals only
        });

        Schema::table('places', function (Blueprint $table) {
            $table->decimal('fee_local_adult', 10, 2)->default(0)->after('fee_foreign_child');
            $table->decimal('fee_local_child', 10, 2)->default(0)->after('fee_local_adult');
        });

        Schema::table('trips', function (Blueprint $table) {
            $table->string('traveller_type', 10)->default('foreign')->after('infants');
            $table->boolean('refreshments')->default(false)->after('guide_language');
        });

        Schema::table('vehicles', function (Blueprint $table) {
            $table->text('description')->nullable()->after('example_model');
            $table->json('amenities')->nullable()->after('luggage_capacity');
            $table->boolean('is_rentable')->default(true)->after('amenities');
            $table->string('provider_name', 120)->nullable()->after('is_rentable');
            $table->string('provider_phone', 30)->nullable()->after('provider_name');
            $table->string('provider_email', 190)->nullable()->after('provider_phone');
            $table->string('provider_website', 255)->nullable()->after('provider_email');
        });

        Schema::table('hotels', function (Blueprint $table) {
            $table->text('description')->nullable()->after('name');
        });

        // Sensible services for the existing fleet (admins can change them).
        $amenities = [
            'budget' => ['air_conditioning', 'water', 'charging'],
            'premium' => ['air_conditioning', 'water', 'charging', 'wifi', 'soft_drinks'],
            'luxury' => ['air_conditioning', 'water', 'charging', 'wifi', 'soft_drinks', 'snacks', 'child_seat'],
        ];
        foreach ($amenities as $tier => $list) {
            DB::table('vehicles')->where('tier', $tier)->whereNull('amenities')->update(['amenities' => json_encode($list)]);
        }
    }

    public function down(): void
    {
        Schema::table('hotels', fn (Blueprint $table) => $table->dropColumn('description'));
        Schema::table('vehicles', fn (Blueprint $table) => $table->dropColumn(['description', 'amenities', 'is_rentable', 'provider_name', 'provider_phone', 'provider_email', 'provider_website']));
        Schema::table('trips', fn (Blueprint $table) => $table->dropColumn(['traveller_type', 'refreshments']));
        Schema::table('places', fn (Blueprint $table) => $table->dropColumn(['fee_local_adult', 'fee_local_child']));
        Schema::table('users', fn (Blueprint $table) => $table->dropColumn(['nationality', 'traveller_type', 'nic']));
    }
};
