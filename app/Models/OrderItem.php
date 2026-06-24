<?php

namespace App\Models;

use App\Models\Concerns\HasCuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrderItem extends Model
{
    use HasCuid;

    protected $table = 'OrderItem';

    public static $snakeAttributes = false;

    public $incrementing = false;

    protected $keyType = 'string';

    const CREATED_AT = 'createdAt';

    const UPDATED_AT = null;

    protected $fillable = [
        'orderId',
        'productId',
        'variantId',
        'variantLabel',
        'quantity',
        'unitPriceInPHP',
        'totalPriceInPHP',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
            'unitPriceInPHP' => 'decimal:2',
            'totalPriceInPHP' => 'decimal:2',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class, 'orderId');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'productId');
    }
}
