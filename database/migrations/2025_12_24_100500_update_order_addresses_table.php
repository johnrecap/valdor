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
        Schema::table('order_addresses', function (Blueprint $table) {
            // Add new governorate-based address fields if they don't exist
            if (!Schema::hasColumn('order_addresses', 'governorate')) {
                $table->string('governorate', 100)->nullable()->after('label');
            }
            if (!Schema::hasColumn('order_addresses', 'city')) {
                $table->string('city', 100)->nullable()->after('governorate');
            }
            if (!Schema::hasColumn('order_addresses', 'street')) {
                $table->string('street', 200)->nullable()->after('city');
            }
            if (!Schema::hasColumn('order_addresses', 'building_number')) {
                $table->string('building_number', 50)->nullable()->after('street');
            }
            if (!Schema::hasColumn('order_addresses', 'full_address')) {
                $table->text('full_address')->nullable()->after('apartment');
            }
            if (!Schema::hasColumn('order_addresses', 'phone')) {
                $table->string('phone', 50)->nullable()->after('full_address');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('order_addresses', function (Blueprint $table) {
            $table->dropColumn(['governorate', 'city', 'street', 'building_number', 'full_address', 'phone']);
        });
    }
};
