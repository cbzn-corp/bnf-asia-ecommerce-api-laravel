<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('PlatformSetting', 'deliveryFeeAtCheckoutEnabled')) {
            Schema::table('PlatformSetting', function (Blueprint $table) {
                $table->boolean('deliveryFeeAtCheckoutEnabled')->default(true);
            });
        }

        if (! Schema::hasColumn('Order', 'deliveryFeeDeferred')) {
            Schema::table('Order', function (Blueprint $table) {
                $table->boolean('deliveryFeeDeferred')->default(false);
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('PlatformSetting', 'deliveryFeeAtCheckoutEnabled')) {
            Schema::table('PlatformSetting', function (Blueprint $table) {
                $table->dropColumn('deliveryFeeAtCheckoutEnabled');
            });
        }

        if (Schema::hasColumn('Order', 'deliveryFeeDeferred')) {
            Schema::table('Order', function (Blueprint $table) {
                $table->dropColumn('deliveryFeeDeferred');
            });
        }
    }
};
