<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('PlatformSetting', 'requireLoginToAddToCart')) {
            Schema::table('PlatformSetting', function (Blueprint $table) {
                $table->boolean('requireLoginToAddToCart')->default(true);
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('PlatformSetting', 'requireLoginToAddToCart')) {
            Schema::table('PlatformSetting', function (Blueprint $table) {
                $table->dropColumn('requireLoginToAddToCart');
            });
        }
    }
};
