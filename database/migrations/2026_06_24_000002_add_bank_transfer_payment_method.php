<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("ALTER TYPE \"PaymentMethod\" ADD VALUE IF NOT EXISTS 'BANK_TRANSFER'");

        if (! Schema::hasColumn('PlatformSetting', 'bankTransferEnabled')) {
            Schema::table('PlatformSetting', function (Blueprint $table) {
                $table->boolean('bankTransferEnabled')->default(true);
            });
        }
    }

    public function down(): void
    {
        Schema::table('PlatformSetting', function (Blueprint $table) {
            $table->dropColumn('bankTransferEnabled');
        });
    }
};
