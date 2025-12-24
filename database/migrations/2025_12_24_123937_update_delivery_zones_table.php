<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('delivery_zones', function (Blueprint $table) {
            // ADD these new columns
            $table->string('governorate_name')->nullable()->after('name');
            $table->decimal('delivery_fee', 8, 2)->default(0)->after('delivery_charge_per_kilo');

            // MAKE NULLABLE - Old location-based fields
            $table->string('latitude')->nullable()->change();
            $table->string('longitude')->nullable()->change();
            $table->string('delivery_radius_kilometer')->nullable()->change();
            $table->text('address')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('delivery_zones', function (Blueprint $table) {
            $table->dropColumn(['governorate_name', 'delivery_fee']);
        });
    }
};
