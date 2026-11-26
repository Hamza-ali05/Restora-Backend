<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Extends the `tenants` table created in 0001_01_01_000000_create_users_table
     * with restaurant location and optional PNG logo path (public disk).
     */
    public function up(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->string('location')->nullable()->after('email');
            $table->string('logo')->nullable()->after('location');
        });
    }

    public function down(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->dropColumn(['location', 'logo']);
        });
    }
};
