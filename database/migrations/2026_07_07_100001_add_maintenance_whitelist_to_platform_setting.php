<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('PlatformSetting', 'maintenanceWhitelistIps')) {
            Schema::table('PlatformSetting', function (Blueprint $table) {
                $table->text('maintenanceWhitelistIps')->nullable();
            });
        }

        if (! Schema::hasColumn('PlatformSetting', 'maintenanceBypassSecret')) {
            Schema::table('PlatformSetting', function (Blueprint $table) {
                $table->string('maintenanceBypassSecret', 128)->nullable();
            });
        }
    }

    public function down(): void
    {
        Schema::table('PlatformSetting', function (Blueprint $table) {
            $table->dropColumn(['maintenanceWhitelistIps', 'maintenanceBypassSecret']);
        });
    }
};
