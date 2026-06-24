<?php

namespace App\Models;

use App\Casts\PostgresTextArray;
use App\Models\Concerns\HasCuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductReview extends Model
{
    use HasCuid;

    protected $table = 'ProductReview';

    public static $snakeAttributes = false;

    public $incrementing = false;

    protected $keyType = 'string';

    const CREATED_AT = 'createdAt';

    const UPDATED_AT = null;

    protected $fillable = [
        'productId',
        'userId',
        'orderId',
        'authorName',
        'rating',
        'comment',
        'isApproved',
        'isVerifiedPurchase',
        'photos',
    ];

    protected function casts(): array
    {
        return [
            'rating' => 'integer',
            'isApproved' => 'boolean',
            'isVerifiedPurchase' => 'boolean',
            'photos' => PostgresTextArray::class,
        ];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'productId');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'userId');
    }
}
