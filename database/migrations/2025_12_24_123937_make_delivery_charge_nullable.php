<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('delivery_zones', function (Blueprint $table) {
            // CRITICAL: Make this nullable for new governorate-based logic
            $table->decimal('delivery_charge_per_kilo', 8, 2)->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('delivery_zones', function (Blueprint $table) {
            $table->decimal('delivery_charge_per_kilo', 8, 2)->nullable(false)->change();
        });
    }
};
