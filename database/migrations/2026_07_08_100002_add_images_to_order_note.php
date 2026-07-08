<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('OrderNote', 'images')) {
            DB::statement('ALTER TABLE "OrderNote" ADD COLUMN "images" TEXT[] NOT NULL DEFAULT \'{}\'::TEXT[]');
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('OrderNote', 'images')) {
            DB::statement('ALTER TABLE "OrderNote" DROP COLUMN "images"');
        }
    }
};
