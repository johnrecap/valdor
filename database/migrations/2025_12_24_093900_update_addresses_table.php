<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('addresses', function (Blueprint $table) {
            // Add new governorate-based address fields
            $table->string('governorate', 100)->nullable()->after('label');
            $table->string('city', 100)->nullable()->after('governorate');
            $table->string('street', 200)->nullable()->after('city');
            $table->string('building_number', 50)->nullable()->after('street');
            $table->string('phone', 50)->nullable()->after('apartment');

            // Make old fields nullable
            $table->string('address', 500)->nullable()->change();
            $table->string('latitude', 190)->nullable()->change();
            $table->string('longitude', 190)->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('addresses', function (Blueprint $table) {
            $table->dropColumn(['governorate', 'city', 'street', 'building_number', 'phone']);

            // Restore required constraints (may fail if data exists)
            $table->string('address', 500)->nullable(false)->change();
            $table->string('latitude', 190)->nullable(false)->change();
            $table->string('longitude', 190)->nullable(false)->change();
        });
    }
};
