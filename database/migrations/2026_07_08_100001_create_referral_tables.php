<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("
            DO \$\$ BEGIN
                CREATE TYPE \"ReferralCommissionStatus\" AS ENUM ('RECORDED', 'CANCELLED');
            EXCEPTION
                WHEN duplicate_object THEN NULL;
            END \$\$;
        ");

        if (! Schema::hasTable('ReferralPartner')) {
            Schema::create('ReferralPartner', function (Blueprint $table) {
                $table->string('id')->primary();
                $table->string('name');
                $table->string('code')->unique();
                $table->string('email')->nullable();
                $table->decimal('commissionRate', 5, 2)->default(5);
                $table->boolean('isActive')->default(true);
                $table->text('notes')->nullable();
                $table->timestamp('createdAt', 3)->useCurrent();
                $table->timestamp('updatedAt', 3)->useCurrent();
            });
        }

        if (! Schema::hasTable('ReferralPartnerProduct')) {
            Schema::create('ReferralPartnerProduct', function (Blueprint $table) {
                $table->string('partnerId');
                $table->string('productId');

                $table->primary(['partnerId', 'productId']);
                $table->index('productId');
                $table->foreign('partnerId')->references('id')->on('ReferralPartner')->cascadeOnDelete();
                $table->foreign('productId')->references('id')->on('Product')->cascadeOnDelete();
            });
        }

        if (! Schema::hasTable('ReferralClick')) {
            Schema::create('ReferralClick', function (Blueprint $table) {
                $table->string('id')->primary();
                $table->string('partnerId');
                $table->string('productId')->nullable();
                $table->string('landingPath')->nullable();
                $table->string('sessionId')->nullable();
                $table->timestamp('createdAt', 3)->useCurrent();

                $table->index('partnerId');
                $table->index('productId');
                $table->index('createdAt');
                $table->foreign('partnerId')->references('id')->on('ReferralPartner')->cascadeOnDelete();
                $table->foreign('productId')->references('id')->on('Product')->nullOnDelete();
            });
        }

        if (! Schema::hasTable('ReferralCommission')) {
            Schema::create('ReferralCommission', function (Blueprint $table) {
                $table->string('id')->primary();
                $table->string('partnerId');
                $table->string('orderId')->unique();
                $table->decimal('eligibleSubtotalInPHP', 12, 2);
                $table->decimal('commissionRate', 5, 2);
                $table->decimal('commissionAmountInPHP', 12, 2);
                $table->jsonb('lineItems')->default('[]');
                $table->timestamp('createdAt', 3)->useCurrent();

                $table->index('partnerId');
                $table->index('createdAt');
                $table->foreign('partnerId')->references('id')->on('ReferralPartner')->cascadeOnDelete();
                $table->foreign('orderId')->references('id')->on('Order')->cascadeOnDelete();
            });
            DB::statement('ALTER TABLE "ReferralCommission" ADD COLUMN "status" "ReferralCommissionStatus" NOT NULL DEFAULT \'RECORDED\'');
            DB::statement('CREATE INDEX "ReferralCommission_status_idx" ON "ReferralCommission" ("status")');
        }

        if (! Schema::hasColumn('Order', 'referralPartnerId')) {
            Schema::table('Order', function (Blueprint $table) {
                $table->string('referralPartnerId')->nullable();
                $table->string('referralCode')->nullable();
                $table->index('referralPartnerId');
                $table->foreign('referralPartnerId')->references('id')->on('ReferralPartner')->nullOnDelete();
            });
        }

        DB::statement("
            UPDATE \"Role\"
            SET permissions = array_append(permissions, 'referrals.manage'),
                \"updatedAt\" = NOW()
            WHERE \"key\" = 'ADMIN'
              AND NOT ('referrals.manage' = ANY(permissions))
        ");
    }

    public function down(): void
    {
        if (Schema::hasColumn('Order', 'referralPartnerId')) {
            Schema::table('Order', function (Blueprint $table) {
                $table->dropForeign(['referralPartnerId']);
                $table->dropColumn(['referralPartnerId', 'referralCode']);
            });
        }

        Schema::dropIfExists('ReferralCommission');
        Schema::dropIfExists('ReferralClick');
        Schema::dropIfExists('ReferralPartnerProduct');
        Schema::dropIfExists('ReferralPartner');

        DB::statement('DROP TYPE IF EXISTS "ReferralCommissionStatus"');

        DB::statement("
            UPDATE \"Role\"
            SET permissions = array_remove(permissions, 'referrals.manage'),
                \"updatedAt\" = NOW()
            WHERE 'referrals.manage' = ANY(permissions)
        ");
    }
};
