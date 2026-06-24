<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('OrderItem', 'createdAt')) {
            Schema::table('OrderItem', function (Blueprint $table) {
                $table->timestamp('createdAt', 3)->useCurrent();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('OrderItem', 'createdAt')) {
            Schema::table('OrderItem', function (Blueprint $table) {
                $table->dropColumn('createdAt');
            });
        }
    }
};
