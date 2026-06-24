<?php

/**
 * BNF Asia e-commerce schema — single source of truth for all application tables.
 * Managed only via ecommerce-api-laravel (php artisan migrate).
 * Enum/table creation is idempotent for safe re-runs on partially migrated databases.
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $this->createEnums();
        $this->createTables();
        $this->seedMigrationData();
    }

    private function tableExists(string $table): bool
    {
        $row = DB::selectOne(
            "SELECT 1 FROM pg_catalog.pg_class c
             JOIN pg_catalog.pg_namespace n ON n.oid = c.relnamespace
             WHERE n.nspname = ANY (current_schemas(false))
               AND c.relname = ?
               AND c.relkind IN ('r', 'p')
             LIMIT 1",
            [$table]
        );

        return $row !== null;
    }

    private function createEnumIfNotExists(string $name, string $values): void
    {
        DB::statement("
            DO \$\$ BEGIN
                CREATE TYPE \"{$name}\" AS ENUM ({$values});
            EXCEPTION
                WHEN duplicate_object THEN NULL;
            END \$\$;
        ");
    }

    public function down(): void
    {
        $tables = [
            'SupportMessage',
            'SupportConversation',
            'OrderItem',
            'OrderRequest',
            'OrderStatusHistory',
            'OrderNote',
            'ShipmentEvent',
            'WishlistItem',
            'StockAlert',
            'ProductReview',
            'BundleItem',
            'CollectionProduct',
            'PromotionProduct',
            'Order',
            'Address',
            'AbandonedCart',
            'PaymentLog',
            'AuditLog',
            'ProductVariant',
            'Product',
            'Category',
            'User',
            'Role',
            'EmailTemplate',
            'PickupLocation',
            'ShippingRate',
            'Promotion',
            'ProductBundle',
            'Collection',
            'ContentBlock',
            'PlatformSetting',
        ];

        foreach ($tables as $table) {
            Schema::dropIfExists($table);
        }

        $enums = [
            'OrderRequestStatus',
            'OrderRequestType',
            'RefundStatus',
            'PromotionType',
            'ShippingStatus',
            'PaymentStatus',
            'CollectionType',
            'DeliveryMethod',
            'MessageSenderType',
            'ConversationStatus',
            'QuoteStatus',
            'PaymentMethod',
            'Currency',
        ];

        foreach ($enums as $enum) {
            DB::statement("DROP TYPE IF EXISTS \"{$enum}\"");
        }
    }

    private function createEnums(): void
    {
        $this->createEnumIfNotExists('Currency', "'PHP', 'USD'");
        $this->createEnumIfNotExists('PaymentMethod', "'PAYMONGO_GCASH', 'PAYMONGO_MAYA', 'STRIPE_CARD', 'COD', 'BANK_TRANSFER', 'BNPL_INSTALLMENT', 'SUPPORT_ASSISTED'");
        $this->createEnumIfNotExists('QuoteStatus', "'NONE', 'PENDING_REVIEW', 'QUOTE_SENT', 'ACCEPTED', 'CANCELLED'");
        $this->createEnumIfNotExists('ConversationStatus', "'OPEN', 'RESOLVED'");
        $this->createEnumIfNotExists('MessageSenderType', "'CUSTOMER', 'STAFF', 'SYSTEM'");
        $this->createEnumIfNotExists('DeliveryMethod', "'DELIVERY', 'PICKUP'");
        $this->createEnumIfNotExists('CollectionType', "'MANUAL', 'AUTOMATED'");
        $this->createEnumIfNotExists('PaymentStatus', "'UNPAID', 'PAID', 'FAILED', 'REFUNDED'");
        $this->createEnumIfNotExists('ShippingStatus', "'PENDING', 'PROCESSING', 'SHIPPED', 'DELIVERED', 'CANCELLED'");
        $this->createEnumIfNotExists('PromotionType', "'PERCENT', 'FIXED'");
        $this->createEnumIfNotExists('RefundStatus', "'NONE', 'REQUESTED', 'PROCESSED', 'REJECTED'");
        $this->createEnumIfNotExists('OrderRequestType', "'CANCEL', 'RETURN'");
        $this->createEnumIfNotExists('OrderRequestStatus', "'PENDING', 'APPROVED', 'REJECTED'");
    }

    private function createTableIfNotExists(string $table, callable $callback): void
    {
        if (! $this->tableExists($table)) {
            Schema::create($table, $callback);
        }
    }

    private function createTables(): void
    {
        $this->createTableIfNotExists('Role', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->string('key')->unique();
            $table->string('name');
            $table->text('description')->nullable();
            $table->boolean('isSystem')->default(false);
            $table->boolean('isStaff')->default(false);
            $table->timestamp('createdAt', 3)->useCurrent();
            $table->timestamp('updatedAt', 3)->useCurrent();
        });
        DB::statement('ALTER TABLE "Role" ADD COLUMN "permissions" TEXT[] NOT NULL DEFAULT \'{}\'::TEXT[]');

        $this->createTableIfNotExists('User', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->string('email')->unique();
            $table->string('passwordHash');
            $table->string('roleId');
            $table->boolean('isActive')->default(true);
            $table->boolean('marketingOptIn')->default(false);
            $table->timestamp('createdAt', 3)->useCurrent();
            $table->timestamp('updatedAt', 3)->useCurrent();

            $table->foreign('roleId')->references('id')->on('Role');
        });

        $this->createTableIfNotExists('Category', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->string('name');
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->string('imageUrl')->nullable();
            $table->integer('sortOrder')->default(0);
            $table->string('parentId')->nullable();
            $table->timestamp('createdAt', 3)->useCurrent();
            $table->timestamp('updatedAt', 3)->useCurrent();

            $table->index('parentId');
            // Self-referential FK is added in addDeferredForeignKeys() — Laravel emits
            // foreign keys before string primary keys on PostgreSQL.
        });

        $this->createTableIfNotExists('Product', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->string('sku')->nullable()->unique();
            $table->string('name');
            $table->string('slug')->unique();
            $table->text('shortDescription')->nullable();
            $table->text('description');
            $table->decimal('priceInPHP', 12, 2);
            $table->decimal('compareAtPrice', 12, 2)->nullable();
            $table->double('weightInGrams');
            $table->integer('stockQuantity');
            $table->boolean('isFeatured')->default(false);
            $table->boolean('isNew')->default(false);
            $table->boolean('isBestSeller')->default(false);
            $table->boolean('isOnSale')->default(false);
            $table->boolean('isPublished')->default(true);
            $table->boolean('hideWhenOutOfStock')->default(false);
            $table->boolean('installationAvailable')->default(false);
            $table->decimal('installationFeeInPHP', 12, 2)->nullable();
            $table->double('rating')->default(0);
            $table->integer('reviewCount')->default(0);
            $table->integer('sortOrder')->default(0);
            $table->string('categoryId')->nullable();
            $table->timestamp('createdAt', 3)->useCurrent();
            $table->timestamp('updatedAt', 3)->useCurrent();

            $table->index('categoryId');
            $table->index('isFeatured');
            $table->index('isOnSale');
            $table->index('name');
            $table->index('sku');
            $table->foreign('categoryId')->references('id')->on('Category')->nullOnDelete();
        });
        DB::statement('ALTER TABLE "Product" ADD COLUMN "images" TEXT[] NOT NULL');
        DB::statement('ALTER TABLE "Product" ADD COLUMN "features" TEXT[] NOT NULL DEFAULT \'{}\'::TEXT[]');

        $this->createTableIfNotExists('ContentBlock', function (Blueprint $table) {
            $table->string('key')->primary();
            $table->jsonb('value');
            $table->timestamp('updatedAt', 3)->useCurrent();
        });

        $this->createTableIfNotExists('PlatformSetting', function (Blueprint $table) {
            $table->string('id')->primary()->default('default');
            $table->decimal('phpPerUsd', 12, 4)->default(56.25);
            $table->boolean('freeShippingEnabled')->default(true);
            $table->decimal('freeShippingMinPHP', 12, 2)->default(50000);
            $table->decimal('vatRatePercent', 5, 2)->default(12);
            $table->boolean('vatEnabled')->default(true);
            $table->string('paymongoPublicKey')->nullable();
            $table->boolean('paymongoEnabled')->default(false);
            $table->string('stripePublishableKey')->nullable();
            $table->boolean('stripeEnabled')->default(false);
            $table->integer('lowStockThreshold')->default(5);
            $table->boolean('bnplEnabled')->default(false);
            $table->boolean('abandonedCartEnabled')->default(true);
            $table->integer('abandonedCartHours')->default(24);
            $table->boolean('supportAssistedCheckoutEnabled')->default(false);
            $table->integer('quoteStaleAlertDays')->default(7);
            $table->string('storeName')->nullable();
            $table->string('storeEmail')->nullable();
            $table->string('storePhone')->nullable();
            $table->text('storeAddress')->nullable();
            $table->boolean('checkoutOrderNotesEnabled')->default(true);
            $table->boolean('guestCheckoutEnabled')->default(true);
            $table->boolean('compareEnabled')->default(true);
            $table->boolean('codEnabled')->default(true);
            $table->boolean('bankTransferEnabled')->default(true);
            $table->boolean('paymongoGcashEnabled')->default(true);
            $table->boolean('paymongoMayaEnabled')->default(true);
            $table->boolean('pricesIncludeVat')->default(false);
            $table->string('abandonedCartDiscountCode')->nullable();
            $table->timestamp('updatedAt', 3)->useCurrent();
        });

        $this->createTableIfNotExists('ProductVariant', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->string('productId');
            $table->string('sku')->nullable()->unique();
            $table->string('name');
            $table->jsonb('options')->default('{}');
            $table->decimal('priceInPHP', 12, 2);
            $table->decimal('compareAtPrice', 12, 2)->nullable();
            $table->integer('stockQuantity')->default(0);
            $table->double('weightInGrams')->nullable();
            $table->boolean('isActive')->default(true);
            $table->integer('sortOrder')->default(0);
            $table->timestamp('createdAt', 3)->useCurrent();
            $table->timestamp('updatedAt', 3)->useCurrent();

            $table->index('productId');
            $table->foreign('productId')->references('id')->on('Product')->cascadeOnDelete();
        });
        DB::statement('ALTER TABLE "ProductVariant" ADD COLUMN "images" TEXT[] NOT NULL DEFAULT \'{}\'::TEXT[]');

        $this->createTableIfNotExists('Collection', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->string('name');
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->jsonb('rules')->nullable();
            $table->string('imageUrl')->nullable();
            $table->integer('sortOrder')->default(0);
            $table->boolean('isActive')->default(true);
            $table->timestamp('createdAt', 3)->useCurrent();
            $table->timestamp('updatedAt', 3)->useCurrent();
        });
        DB::statement('ALTER TABLE "Collection" ADD COLUMN "type" "CollectionType" NOT NULL DEFAULT \'MANUAL\'');

        $this->createTableIfNotExists('CollectionProduct', function (Blueprint $table) {
            $table->string('collectionId');
            $table->string('productId');
            $table->integer('sortOrder')->default(0);

            $table->primary(['collectionId', 'productId']);
            $table->index('productId');
            $table->foreign('collectionId')->references('id')->on('Collection')->cascadeOnDelete();
            $table->foreign('productId')->references('id')->on('Product')->cascadeOnDelete();
        });

        $this->createTableIfNotExists('ProductBundle', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->string('name');
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->decimal('discountPercent', 5, 2)->default(10);
            $table->string('imageUrl')->nullable();
            $table->boolean('isActive')->default(true);
            $table->timestamp('createdAt', 3)->useCurrent();
            $table->timestamp('updatedAt', 3)->useCurrent();
        });

        $this->createTableIfNotExists('BundleItem', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->string('bundleId');
            $table->string('productId');
            $table->string('variantId')->nullable();
            $table->integer('quantity')->default(1);

            $table->index('bundleId');
            $table->foreign('bundleId')->references('id')->on('ProductBundle')->cascadeOnDelete();
            $table->foreign('productId')->references('id')->on('Product')->restrictOnDelete();
            $table->foreign('variantId')->references('id')->on('ProductVariant')->nullOnDelete();
        });

        $this->createTableIfNotExists('AbandonedCart', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->string('email')->nullable();
            $table->string('userId')->nullable();
            $table->jsonb('items');
            $table->string('recoveryToken')->unique();
            $table->timestamp('lastActivityAt', 3)->useCurrent();
            $table->timestamp('recoveryEmailSentAt', 3)->nullable();
            $table->timestamp('recoveredAt', 3)->nullable();
            $table->timestamp('createdAt', 3)->useCurrent();
            $table->timestamp('updatedAt', 3)->useCurrent();

            $table->index('email');
            $table->index('lastActivityAt');
        });

        $this->createTableIfNotExists('EmailTemplate', function (Blueprint $table) {
            $table->string('key')->primary();
            $table->string('subject');
            $table->text('bodyText');
            $table->timestamp('updatedAt', 3)->useCurrent();
        });

        $this->createTableIfNotExists('PickupLocation', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->string('name');
            $table->string('address');
            $table->string('city');
            $table->string('province');
            $table->string('phone')->nullable();
            $table->boolean('isActive')->default(true);
            $table->integer('sortOrder')->default(0);
            $table->timestamp('createdAt', 3)->useCurrent();
            $table->timestamp('updatedAt', 3)->useCurrent();
        });

        $this->createTableIfNotExists('ShippingRate', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->string('label');
            $table->string('region');
            $table->string('zone')->nullable();
            $table->decimal('feeInPHP', 12, 2);
            $table->string('estimatedDays')->nullable();
            $table->double('minWeightGrams')->nullable();
            $table->double('maxWeightGrams')->nullable();
            $table->boolean('isActive')->default(true);
            $table->integer('sortOrder')->default(0);
            $table->timestamp('createdAt', 3)->useCurrent();
            $table->timestamp('updatedAt', 3)->useCurrent();
        });

        $this->createTableIfNotExists('Promotion', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->string('code')->unique();
            $table->text('description')->nullable();
            $table->decimal('value', 12, 2);
            $table->decimal('minOrderPHP', 12, 2)->default(0);
            $table->integer('maxUses')->nullable();
            $table->integer('usedCount')->default(0);
            $table->boolean('oneUsePerAccount')->default(false);
            $table->timestamp('startsAt', 3)->nullable();
            $table->timestamp('expiresAt', 3)->nullable();
            $table->boolean('isActive')->default(true);
            $table->timestamp('createdAt', 3)->useCurrent();
            $table->timestamp('updatedAt', 3)->useCurrent();
        });
        DB::statement('ALTER TABLE "Promotion" ADD COLUMN "type" "PromotionType" NOT NULL');

        $this->createTableIfNotExists('PromotionProduct', function (Blueprint $table) {
            $table->string('promotionId');
            $table->string('productId');

            $table->primary(['promotionId', 'productId']);
            $table->index('productId');
            $table->foreign('promotionId')->references('id')->on('Promotion')->cascadeOnDelete();
            $table->foreign('productId')->references('id')->on('Product')->cascadeOnDelete();
        });

        $this->createTableIfNotExists('ProductReview', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->string('productId');
            $table->string('userId')->nullable();
            $table->string('orderId')->nullable();
            $table->string('authorName');
            $table->integer('rating');
            $table->text('comment');
            $table->boolean('isApproved')->default(false);
            $table->boolean('isVerifiedPurchase')->default(false);
            $table->timestamp('createdAt', 3)->useCurrent();

            $table->index('productId');
            $table->index('isApproved');
            $table->foreign('productId')->references('id')->on('Product')->cascadeOnDelete();
            $table->foreign('userId')->references('id')->on('User')->nullOnDelete();
        });
        DB::statement('ALTER TABLE "ProductReview" ADD COLUMN "photos" TEXT[] NOT NULL DEFAULT \'{}\'::TEXT[]');

        $this->createTableIfNotExists('Address', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->string('userId')->nullable();
            $table->string('label')->nullable()->default('Home');
            $table->string('country');
            $table->string('street1');
            $table->string('street2')->nullable();
            $table->string('region')->nullable();
            $table->string('province')->nullable();
            $table->string('city')->nullable();
            $table->string('barangay')->nullable();
            $table->string('postalCode')->nullable();
            $table->boolean('isDefault')->default(false);
            $table->timestamp('createdAt', 3)->useCurrent();
            $table->timestamp('updatedAt', 3)->useCurrent();

            $table->index('userId');
            $table->index('country');
            $table->foreign('userId')->references('id')->on('User')->nullOnDelete();
        });

        $this->createTableIfNotExists('Order', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->string('orderNumber')->unique();
            $table->string('userId')->nullable();
            $table->string('guestEmail')->nullable();
            $table->string('guestPhone')->nullable();
            $table->decimal('exchangeRate', 12, 6);
            $table->decimal('subtotalInPHP', 12, 2)->default(0);
            $table->decimal('taxAmountInPHP', 12, 2)->default(0);
            $table->decimal('discountAmountInPHP', 12, 2)->default(0);
            $table->decimal('shippingFeeInPHP', 12, 2)->default(0);
            $table->string('shippingZone')->nullable();
            $table->string('shippingRateId')->nullable();
            $table->decimal('installationFeeInPHP', 12, 2)->default(0);
            $table->boolean('installationRequested')->default(false);
            $table->decimal('totalAmountInPHP', 12, 2);
            $table->string('promotionCode')->nullable();
            $table->string('paymentSessionId')->nullable();
            $table->string('paymentSessionUrl')->nullable();
            $table->string('pickupLocationId')->nullable();
            $table->string('trackingNumber')->nullable();
            $table->string('carrier')->nullable();
            $table->timestamp('estimatedDeliveryAt', 3)->nullable();
            $table->jsonb('shippingAddress');
            $table->text('customerNote')->nullable();
            $table->timestamp('quoteStaleAt', 3)->nullable();
            $table->decimal('refundAmountInPHP', 12, 2)->nullable();
            $table->text('refundReason')->nullable();
            $table->timestamp('createdAt', 3)->useCurrent();
            $table->timestamp('updatedAt', 3)->useCurrent();

            $table->index('userId');
            $table->index('guestEmail');
            $table->foreign('userId')->references('id')->on('User')->nullOnDelete();
        });
        DB::statement('ALTER TABLE "Order" ADD COLUMN "currency" "Currency" NOT NULL');
        DB::statement('ALTER TABLE "Order" ADD COLUMN "paymentMethod" "PaymentMethod" NOT NULL');
        DB::statement('ALTER TABLE "Order" ADD COLUMN "paymentStatus" "PaymentStatus" NOT NULL DEFAULT \'UNPAID\'');
        DB::statement('ALTER TABLE "Order" ADD COLUMN "shippingStatus" "ShippingStatus" NOT NULL DEFAULT \'PENDING\'');
        DB::statement('ALTER TABLE "Order" ADD COLUMN "refundStatus" "RefundStatus" NOT NULL DEFAULT \'NONE\'');
        DB::statement('ALTER TABLE "Order" ADD COLUMN "deliveryMethod" "DeliveryMethod" NOT NULL DEFAULT \'DELIVERY\'');
        DB::statement('ALTER TABLE "Order" ADD COLUMN "quoteStatus" "QuoteStatus" NOT NULL DEFAULT \'NONE\'');
        DB::statement('CREATE INDEX "Order_paymentStatus_idx" ON "Order" ("paymentStatus")');
        DB::statement('CREATE INDEX "Order_shippingStatus_idx" ON "Order" ("shippingStatus")');
        DB::statement('CREATE INDEX "Order_refundStatus_idx" ON "Order" ("refundStatus")');
        DB::statement('CREATE INDEX "Order_quoteStatus_idx" ON "Order" ("quoteStatus")');

        $this->createTableIfNotExists('SupportConversation', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->string('orderId')->unique();
            $table->timestamp('createdAt', 3)->useCurrent();
            $table->timestamp('updatedAt', 3)->useCurrent();

            $table->index('updatedAt');
            $table->foreign('orderId')->references('id')->on('Order')->cascadeOnDelete();
        });
        DB::statement('ALTER TABLE "SupportConversation" ADD COLUMN "status" "ConversationStatus" NOT NULL DEFAULT \'OPEN\'');
        DB::statement('CREATE INDEX "SupportConversation_status_idx" ON "SupportConversation" ("status")');

        $this->createTableIfNotExists('SupportMessage', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->string('conversationId');
            $table->string('senderUserId')->nullable();
            $table->text('body');
            $table->timestamp('readAt', 3)->nullable();
            $table->timestamp('createdAt', 3)->useCurrent();

            $table->index('conversationId');
            $table->index('createdAt');
            $table->foreign('conversationId')->references('id')->on('SupportConversation')->cascadeOnDelete();
        });
        DB::statement('ALTER TABLE "SupportMessage" ADD COLUMN "senderType" "MessageSenderType" NOT NULL');

        $this->createTableIfNotExists('OrderItem', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->string('orderId');
            $table->string('productId');
            $table->string('variantId')->nullable();
            $table->string('variantLabel')->nullable();
            $table->integer('quantity');
            $table->decimal('unitPriceInPHP', 12, 2);
            $table->decimal('totalPriceInPHP', 12, 2);
            $table->timestamp('createdAt', 3)->useCurrent();

            $table->index('orderId');
            $table->index('productId');
            $table->index('variantId');
            $table->foreign('orderId')->references('id')->on('Order')->cascadeOnDelete();
            $table->foreign('productId')->references('id')->on('Product')->restrictOnDelete();
            $table->foreign('variantId')->references('id')->on('ProductVariant')->nullOnDelete();
        });

        $this->createTableIfNotExists('WishlistItem', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->string('userId');
            $table->string('productId');
            $table->timestamp('createdAt', 3)->useCurrent();

            $table->unique(['userId', 'productId']);
            $table->index('userId');
            $table->foreign('userId')->references('id')->on('User')->cascadeOnDelete();
            $table->foreign('productId')->references('id')->on('Product')->cascadeOnDelete();
        });

        $this->createTableIfNotExists('OrderRequest', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->string('orderId');
            $table->string('userId')->nullable();
            $table->text('reason');
            $table->text('staffNote')->nullable();
            $table->timestamp('createdAt', 3)->useCurrent();
            $table->timestamp('updatedAt', 3)->useCurrent();

            $table->index('orderId');
            $table->foreign('orderId')->references('id')->on('Order')->cascadeOnDelete();
            $table->foreign('userId')->references('id')->on('User')->nullOnDelete();
        });
        DB::statement('ALTER TABLE "OrderRequest" ADD COLUMN "type" "OrderRequestType" NOT NULL');
        DB::statement('ALTER TABLE "OrderRequest" ADD COLUMN "status" "OrderRequestStatus" NOT NULL DEFAULT \'PENDING\'');
        DB::statement('CREATE INDEX "OrderRequest_status_idx" ON "OrderRequest" ("status")');
        DB::statement('CREATE INDEX "OrderRequest_type_idx" ON "OrderRequest" ("type")');

        $this->createTableIfNotExists('StockAlert', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->string('email');
            $table->string('productId');
            $table->string('variantId')->nullable();
            $table->string('userId')->nullable();
            $table->timestamp('notifiedAt', 3)->nullable();
            $table->timestamp('createdAt', 3)->useCurrent();

            $table->unique(['email', 'productId', 'variantId']);
            $table->index('productId');
            $table->index('notifiedAt');
            $table->foreign('productId')->references('id')->on('Product')->cascadeOnDelete();
            $table->foreign('variantId')->references('id')->on('ProductVariant')->cascadeOnDelete();
            $table->foreign('userId')->references('id')->on('User')->nullOnDelete();
        });

        $this->createTableIfNotExists('OrderStatusHistory', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->string('orderId');
            $table->string('field');
            $table->string('fromValue');
            $table->string('toValue');
            $table->string('changedByEmail')->nullable();
            $table->timestamp('createdAt', 3)->useCurrent();

            $table->index('orderId');
            $table->foreign('orderId')->references('id')->on('Order')->cascadeOnDelete();
        });

        $this->createTableIfNotExists('OrderNote', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->string('orderId');
            $table->string('authorEmail');
            $table->text('body');
            $table->timestamp('createdAt', 3)->useCurrent();

            $table->index('orderId');
            $table->foreign('orderId')->references('id')->on('Order')->cascadeOnDelete();
        });

        $this->createTableIfNotExists('AuditLog', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->string('userEmail');
            $table->string('action');
            $table->string('entity');
            $table->string('entityId')->nullable();
            $table->jsonb('details')->nullable();
            $table->timestamp('createdAt', 3)->useCurrent();

            $table->index('entity');
            $table->index('createdAt');
        });

        $this->createTableIfNotExists('PaymentLog', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->string('provider');
            $table->string('orderNumber')->nullable();
            $table->jsonb('payload');
            $table->boolean('signatureValid');
            $table->timestamp('createdAt', 3)->useCurrent();

            $table->index('orderNumber');
        });

        $this->createTableIfNotExists('ShipmentEvent', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->string('orderId');
            $table->string('status');
            $table->text('message');
            $table->string('location')->nullable();
            $table->timestamp('occurredAt', 3)->useCurrent();
            $table->timestamp('createdAt', 3)->useCurrent();

            $table->index('orderId');
            $table->foreign('orderId')->references('id')->on('Order')->cascadeOnDelete();
        });

        $this->addDeferredForeignKeys();
    }

    /**
     * Foreign keys that reference the same table being created must run after the
     * primary key exists (Laravel adds string PKs via a separate ALTER on PostgreSQL).
     */
    private function addDeferredForeignKeys(): void
    {
        if (! $this->tableExists('Category')) {
            return;
        }

        $constraint = DB::selectOne(
            "SELECT 1 FROM pg_constraint WHERE conname = 'category_parentid_foreign' LIMIT 1"
        );

        if ($constraint !== null) {
            return;
        }

        DB::statement('
            ALTER TABLE "Category"
            ADD CONSTRAINT "category_parentid_foreign"
            FOREIGN KEY ("parentId") REFERENCES "Category" ("id") ON DELETE SET NULL
        ');
    }

    private function seedMigrationData(): void
    {
        $now = now();

        DB::table('PlatformSetting')->insertOrIgnore([
            'id' => 'default',
            'updatedAt' => $now,
        ]);

        DB::table('EmailTemplate')->insertOrIgnore([
            [
                'key' => 'order_confirmation',
                'subject' => 'Order confirmed — {{orderNumber}}',
                'bodyText' => "Thank you for your order {{orderNumber}}.\nTotal: {{total}}\nPayment: {{paymentMethod}}",
                'updatedAt' => $now,
            ],
            [
                'key' => 'abandoned_cart',
                'subject' => 'You left items in your cart',
                'bodyText' => "Hi! You left items in your cart at BNF Asia.\nComplete your order: {{recoveryUrl}}",
                'updatedAt' => $now,
            ],
            [
                'key' => 'order_shipped',
                'subject' => 'Your order {{orderNumber}} has shipped',
                'bodyText' => "Your order {{orderNumber}} is on the way.\nCarrier: {{carrier}}\nTracking: {{trackingNumber}}",
                'updatedAt' => $now,
            ],
            [
                'key' => 'order_status',
                'subject' => 'Order {{orderNumber}} — status update',
                'bodyText' => "Hello,\n\nYour order {{orderNumber}} has been updated.\n\nShipping status: {{shippingStatus}}\nPayment status: {{paymentStatus}}\n\nThank you for shopping with BNF Asia.",
                'updatedAt' => $now,
            ],
            [
                'key' => 'payment_reminder',
                'subject' => 'Payment reminder — order {{orderNumber}}',
                'bodyText' => "Hello,\n\nYour order {{orderNumber}} is ready for payment.\n\nTotal due: {{total}}\nPayment method: {{paymentMethod}}\n\nView your order and chat with our team: {{accountUrl}}\n\nThank you for shopping with BNF Asia.",
                'updatedAt' => $now,
            ],
        ]);

        DB::table('PickupLocation')->insertOrIgnore([
            'id' => 'pickup-makati',
            'name' => 'BNF Asia Showroom — Makati',
            'address' => '123 Ayala Ave',
            'city' => 'Makati',
            'province' => 'Metro Manila',
            'phone' => '+63 2 8888 0000',
            'isActive' => true,
            'sortOrder' => 1,
            'createdAt' => $now,
            'updatedAt' => $now,
        ]);

        DB::statement("
            INSERT INTO \"Collection\" (\"id\", \"name\", \"slug\", \"description\", \"type\", \"sortOrder\", \"isActive\", \"createdAt\", \"updatedAt\")
            VALUES
                ('col-bedroom', 'Complete Your Bedroom', 'complete-your-bedroom', 'Curated bedroom furniture sets', 'MANUAL'::\"CollectionType\", 1, true, ?, ?),
                ('col-living', 'Living Room Essentials', 'living-room-essentials', 'Sofas, tables, and storage', 'MANUAL'::\"CollectionType\", 2, true, ?, ?)
            ON CONFLICT (\"id\") DO NOTHING
        ", [$now, $now, $now, $now]);
    }
};
