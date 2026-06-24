<?php

namespace App\Models;

use App\Enums\OrderRequestStatus;
use App\Enums\OrderRequestType;
use App\Models\Concerns\HasCuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrderRequest extends Model
{
    use HasCuid;

    protected $table = 'OrderRequest';

    public static $snakeAttributes = false;

    public $incrementing = false;

    protected $keyType = 'string';

    const CREATED_AT = 'createdAt';

    const UPDATED_AT = 'updatedAt';

    protected $fillable = [
        'orderId',
        'userId',
        'type',
        'status',
        'reason',
        'staffNote',
    ];

    protected function casts(): array
    {
        return [
            'type' => OrderRequestType::class,
            'status' => OrderRequestStatus::class,
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class, 'orderId');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'userId');
    }
}
