<?php

namespace App\Models;

use App\Casts\PostgresTextArray;
use App\Models\Concerns\HasCuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ProductVariant extends Model
{
    use HasCuid;

    protected $table = 'ProductVariant';

    public static $snakeAttributes = false;

    public $incrementing = false;

    protected $keyType = 'string';

    const CREATED_AT = 'createdAt';

    const UPDATED_AT = 'updatedAt';

    protected $fillable = [
        'productId',
        'sku',
        'name',
        'options',
        'priceInPHP',
        'compareAtPrice',
        'stockQuantity',
        'weightInGrams',
        'images',
        'isActive',
        'sortOrder',
    ];

    protected function casts(): array
    {
        return [
            'options' => 'array',
            'priceInPHP' => 'decimal:2',
            'compareAtPrice' => 'decimal:2',
            'stockQuantity' => 'integer',
            'weightInGrams' => 'float',
            'images' => PostgresTextArray::class,
            'isActive' => 'boolean',
            'sortOrder' => 'integer',
        ];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'productId');
    }

    public function bundleItems(): HasMany
    {
        return $this->hasMany(BundleItem::class, 'variantId');
    }
}
