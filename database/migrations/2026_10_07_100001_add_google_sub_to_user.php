<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('User', 'googleSub')) {
            Schema::table('User', function (Blueprint $table) {
                $table->string('googleSub')->nullable()->unique();
            });
        }

        DB::statement('ALTER TABLE "User" ALTER COLUMN "passwordHash" DROP NOT NULL');
    }

    public function down(): void
    {
        DB::statement('UPDATE "User" SET "passwordHash" = \'\' WHERE "passwordHash" IS NULL');
        DB::statement('ALTER TABLE "User" ALTER COLUMN "passwordHash" SET NOT NULL');

        if (Schema::hasColumn('User', 'googleSub')) {
            Schema::table('User', function (Blueprint $table) {
                $table->dropUnique(['googleSub']);
                $table->dropColumn('googleSub');
            });
        }
    }
};
