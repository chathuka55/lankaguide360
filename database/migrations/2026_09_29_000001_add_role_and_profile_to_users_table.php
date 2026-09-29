<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * SRS 7.3 users: role, phone, country, age.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->enum('role', ['traveller', 'agent', 'admin'])->default('traveller')->after('password')->index();
            $table->string('phone', 30)->nullable()->after('role');
            $table->string('country', 80)->nullable()->after('phone');
            $table->unsignedTinyInteger('age')->nullable()->after('country');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex(['role']);
            $table->dropColumn(['role', 'phone', 'country', 'age']);
        });
    }
};
