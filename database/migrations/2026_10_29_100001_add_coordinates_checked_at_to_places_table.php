<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Coordinates checked by a person (the correction list in data/place_coordinates.php or an admin
 * edit). The Wikipedia importer never overwrites them.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('places', function (Blueprint $table) {
            $table->timestamp('coordinates_checked_at')->nullable()->after('lng');
        });
    }

    public function down(): void
    {
        Schema::table('places', function (Blueprint $table) {
            $table->dropColumn('coordinates_checked_at');
        });
    }
};
