<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('CartAddEvent')) {
            return;
        }

        Schema::create('CartAddEvent', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->string('userId');
            $table->string('productId');
            $table->string('variantId')->nullable();
            $table->string('productName');
            $table->string('variantName')->nullable();
            $table->string('slug');
            $table->unsignedInteger('qty');
            $table->decimal('priceInPHP', 12, 2);
            $table->timestamp('createdAt', 3)->useCurrent();

            $table->index('createdAt');
            $table->index(['productId', 'createdAt']);
            $table->index(['userId', 'createdAt']);
            $table->foreign('userId')->references('id')->on('User')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('CartAddEvent');
    }
};
