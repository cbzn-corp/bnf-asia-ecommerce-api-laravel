<?php

namespace App\Models;

use App\Enums\ReferralCommissionStatus;
use App\Models\Concerns\HasCuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReferralCommission extends Model
{
    use HasCuid;

    protected $table = 'ReferralCommission';

    public static $snakeAttributes = false;

    public $incrementing = false;

    protected $keyType = 'string';

    const CREATED_AT = 'createdAt';

    const UPDATED_AT = null;

    protected $fillable = [
        'partnerId',
        'orderId',
        'eligibleSubtotalInPHP',
        'commissionRate',
        'commissionAmountInPHP',
        'lineItems',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'eligibleSubtotalInPHP' => 'decimal:2',
            'commissionRate' => 'decimal:2',
            'commissionAmountInPHP' => 'decimal:2',
            'lineItems' => 'array',
            'status' => ReferralCommissionStatus::class,
        ];
    }

    public function partner(): BelongsTo
    {
        return $this->belongsTo(ReferralPartner::class, 'partnerId');
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class, 'orderId');
    }
}
