<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PromotionProduct extends Model
{
    protected $table = 'PromotionProduct';

    public static $snakeAttributes = false;

    public $incrementing = false;

    public $timestamps = false;

    protected $fillable = [
        'promotionId',
        'productId',
    ];

    public function getKeyName(): array
    {
        return ['promotionId', 'productId'];
    }

    public function getKey()
    {
        return [
            'promotionId' => $this->getAttribute('promotionId'),
            'productId' => $this->getAttribute('productId'),
        ];
    }

    protected function setKeysForSaveQuery($query)
    {
        foreach ($this->getKeyName() as $keyName) {
            $query->where($keyName, '=', $this->getAttribute($keyName));
        }

        return $query;
    }

    public function promotion(): BelongsTo
    {
        return $this->belongsTo(Promotion::class, 'promotionId');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'productId');
    }
}
