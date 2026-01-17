<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * إضافة حقل البلد (مصر/السعودية) لجداول العناوين
     */
    public function up(): void
    {
        // Add country to addresses table
        if (!Schema::hasColumn('addresses', 'country')) {
            Schema::table('addresses', function (Blueprint $table) {
                $table->string('country', 5)->default('EG')->after('user_id');
            });
        }

        // Add country to order_addresses table
        if (!Schema::hasColumn('order_addresses', 'country')) {
            Schema::table('order_addresses', function (Blueprint $table) {
                $table->string('country', 5)->default('EG')->after('user_id');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasColumn('addresses', 'country')) {
            Schema::table('addresses', function (Blueprint $table) {
                $table->dropColumn('country');
            });
        }

        if (Schema::hasColumn('order_addresses', 'country')) {
            Schema::table('order_addresses', function (Blueprint $table) {
                $table->dropColumn('country');
            });
        }
    }
};
