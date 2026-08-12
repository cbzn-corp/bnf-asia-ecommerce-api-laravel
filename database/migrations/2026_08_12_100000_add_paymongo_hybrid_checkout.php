<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("ALTER TYPE \"PaymentMethod\" ADD VALUE IF NOT EXISTS 'PAYMONGO'");

        if (! Schema::hasColumn('Order', 'paymongoPaymentType')) {
            Schema::table('Order', function (Blueprint $table) {
                $table->string('paymongoPaymentType')->nullable();
            });
        }

        if (! Schema::hasColumn('PlatformSetting', 'paymongoPaymentMethodTypes')) {
            Schema::table('PlatformSetting', function (Blueprint $table) {
                // JSON array of PayMongo Checkout payment_method_types (e.g. qrph, dob)
                $table->json('paymongoPaymentMethodTypes')->nullable();
            });
        }

        DB::table('PlatformSetting')
            ->whereNull('paymongoPaymentMethodTypes')
            ->update([
                'paymongoPaymentMethodTypes' => json_encode(['qrph', 'dob']),
            ]);
    }

    public function down(): void
    {
        Schema::table('Order', function (Blueprint $table) {
            if (Schema::hasColumn('Order', 'paymongoPaymentType')) {
                $table->dropColumn('paymongoPaymentType');
            }
        });

        Schema::table('PlatformSetting', function (Blueprint $table) {
            if (Schema::hasColumn('PlatformSetting', 'paymongoPaymentMethodTypes')) {
                $table->dropColumn('paymongoPaymentMethodTypes');
            }
        });
    }
};
