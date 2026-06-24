<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('PlatformSetting', 'maintenanceModeEnabled')) {
            Schema::table('PlatformSetting', function (Blueprint $table) {
                $table->boolean('maintenanceModeEnabled')->default(false);
            });
        }

        if (! Schema::hasColumn('PlatformSetting', 'maintenanceMessage')) {
            Schema::table('PlatformSetting', function (Blueprint $table) {
                $table->text('maintenanceMessage')->nullable();
            });
        }

        if (! Schema::hasColumn('EmailTemplate', 'bodyHtml')) {
            Schema::table('EmailTemplate', function (Blueprint $table) {
                $table->text('bodyHtml')->nullable();
            });
        }
    }

    public function down(): void
    {
        Schema::table('PlatformSetting', function (Blueprint $table) {
            $table->dropColumn(['maintenanceModeEnabled', 'maintenanceMessage']);
        });

        Schema::table('EmailTemplate', function (Blueprint $table) {
            $table->dropColumn('bodyHtml');
        });
    }
};
