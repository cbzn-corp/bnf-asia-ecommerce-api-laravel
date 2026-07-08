<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReferralPartnerProduct extends Model
{
    protected $table = 'ReferralPartnerProduct';

    public static $snakeAttributes = false;

    public $incrementing = false;

    public $timestamps = false;

    protected $fillable = [
        'partnerId',
        'productId',
    ];

    public function getKeyName(): array
    {
        return ['partnerId', 'productId'];
    }

    public function getKey()
    {
        return [
            'partnerId' => $this->getAttribute('partnerId'),
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

    public function partner(): BelongsTo
    {
        return $this->belongsTo(ReferralPartner::class, 'partnerId');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'productId');
    }
}
