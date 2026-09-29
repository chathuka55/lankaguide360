<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Phase 4: rejected photos are remembered so importers skip them.
     */
    public function up(): void
    {
        Schema::table('media', function (Blueprint $table) {
            $table->enum('status', ['draft', 'published', 'rejected'])->default('draft')->change();
        });
    }

    public function down(): void
    {
        DB::table('media')->where('status', 'rejected')->delete();

        Schema::table('media', function (Blueprint $table) {
            $table->enum('status', ['draft', 'published'])->default('draft')->change();
        });
    }
};
