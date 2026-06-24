<?php

namespace App\Models;

use App\Casts\PostgresTextArray;
use App\Models\Concerns\HasCuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Product extends Model
{
    use HasCuid;

    protected $table = 'Product';

    public static $snakeAttributes = false;

    public $incrementing = false;

    protected $keyType = 'string';

    const CREATED_AT = 'createdAt';

    const UPDATED_AT = 'updatedAt';

    protected $fillable = [
        'sku',
        'name',
        'slug',
        'shortDescription',
        'description',
        'priceInPHP',
        'compareAtPrice',
        'weightInGrams',
        'stockQuantity',
        'images',
        'features',
        'isFeatured',
        'isNew',
        'isBestSeller',
        'isOnSale',
        'isPublished',
        'hideWhenOutOfStock',
        'installationAvailable',
        'installationFeeInPHP',
        'rating',
        'reviewCount',
        'sortOrder',
        'categoryId',
    ];

    protected function casts(): array
    {
        return [
            'priceInPHP' => 'decimal:2',
            'compareAtPrice' => 'decimal:2',
            'weightInGrams' => 'float',
            'stockQuantity' => 'integer',
            'images' => PostgresTextArray::class,
            'features' => PostgresTextArray::class,
            'isFeatured' => 'boolean',
            'isNew' => 'boolean',
            'isBestSeller' => 'boolean',
            'isOnSale' => 'boolean',
            'isPublished' => 'boolean',
            'hideWhenOutOfStock' => 'boolean',
            'installationAvailable' => 'boolean',
            'installationFeeInPHP' => 'decimal:2',
            'rating' => 'float',
            'reviewCount' => 'integer',
            'sortOrder' => 'integer',
        ];
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class, 'categoryId');
    }

    public function orderItems(): HasMany
    {
        return $this->hasMany(OrderItem::class, 'productId');
    }

    public function reviews(): HasMany
    {
        return $this->hasMany(ProductReview::class, 'productId');
    }

    public function wishlistItems(): HasMany
    {
        return $this->hasMany(WishlistItem::class, 'productId');
    }

    public function stockAlerts(): HasMany
    {
        return $this->hasMany(StockAlert::class, 'productId');
    }

    public function variants(): HasMany
    {
        return $this->hasMany(ProductVariant::class, 'productId');
    }

    public function collections(): HasMany
    {
        return $this->hasMany(CollectionProduct::class, 'productId');
    }

    public function bundleItems(): HasMany
    {
        return $this->hasMany(BundleItem::class, 'productId');
    }

    public function voucherLinks(): HasMany
    {
        return $this->hasMany(PromotionProduct::class, 'productId');
    }
}
