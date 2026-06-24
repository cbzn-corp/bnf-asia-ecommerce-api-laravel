<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('PlatformSetting', 'customerChatEnabled')) {
            Schema::table('PlatformSetting', function (Blueprint $table) {
                $table->boolean('customerChatEnabled')->default(true);
            });
        }
    }

    public function down(): void
    {
        Schema::table('PlatformSetting', function (Blueprint $table) {
            $table->dropColumn('customerChatEnabled');
        });
    }
};
