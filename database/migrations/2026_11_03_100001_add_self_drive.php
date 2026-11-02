<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Self-drive hire for Sri Lankan residents (Nov 2026): self-drive rates per vehicle and the
 * driver's licence on a trip. The agent checks the licence when handing over the vehicle.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('vehicles', function (Blueprint $table) {
            $table->boolean('self_drive')->default(false)->after('is_rentable');
            $table->decimal('self_drive_day_rate', 10, 2)->nullable()->after('self_drive');
            $table->decimal('self_drive_deposit', 10, 2)->nullable()->after('self_drive_day_rate');
            $table->unsignedSmallInteger('self_drive_km_per_day')->nullable()->after('self_drive_deposit');
        });

        Schema::table('trips', function (Blueprint $table) {
            $table->boolean('self_drive')->default(false)->after('vehicle_id');
            $table->string('driver_name', 120)->nullable()->after('self_drive');
            $table->string('driver_licence_no', 20)->nullable()->after('driver_name');
            $table->date('driver_licence_expiry')->nullable()->after('driver_licence_no');
        });

        foreach (require database_path('seeders/data/self_drive.php') as $type => [$rate, $deposit, $km]) {
            DB::table('vehicles')->where('type', $type)->where('self_drive', false)->whereNull('self_drive_day_rate')->update([
                'self_drive' => true,
                'self_drive_day_rate' => $rate,
                'self_drive_deposit' => $deposit,
                'self_drive_km_per_day' => $km,
            ]);
        }
    }

    public function down(): void
    {
        Schema::table('trips', fn (Blueprint $table) => $table->dropColumn(['self_drive', 'driver_name', 'driver_licence_no', 'driver_licence_expiry']));
        Schema::table('vehicles', fn (Blueprint $table) => $table->dropColumn(['self_drive', 'self_drive_day_rate', 'self_drive_deposit', 'self_drive_km_per_day']));
    }
};
