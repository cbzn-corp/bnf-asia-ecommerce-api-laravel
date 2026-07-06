<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('PlatformSetting', 'reviewsEnabled')) {
            Schema::table('PlatformSetting', function (Blueprint $table) {
                $table->boolean('reviewsEnabled')->default(true);
            });
        }

        if (! Schema::hasColumn('PlatformSetting', 'reviewsSubmissionEnabled')) {
            Schema::table('PlatformSetting', function (Blueprint $table) {
                $table->boolean('reviewsSubmissionEnabled')->default(true);
            });
        }
    }

    public function down(): void
    {
        Schema::table('PlatformSetting', function (Blueprint $table) {
            if (Schema::hasColumn('PlatformSetting', 'reviewsSubmissionEnabled')) {
                $table->dropColumn('reviewsSubmissionEnabled');
            }
            if (Schema::hasColumn('PlatformSetting', 'reviewsEnabled')) {
                $table->dropColumn('reviewsEnabled');
            }
        });
    }
};
