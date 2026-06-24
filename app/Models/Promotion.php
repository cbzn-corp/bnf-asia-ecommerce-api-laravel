<?php

namespace App\Models;

use App\Enums\PromotionType;
use App\Models\Concerns\HasCuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Promotion extends Model
{
    use HasCuid;

    protected $table = 'Promotion';

    public static $snakeAttributes = false;

    public $incrementing = false;

    protected $keyType = 'string';

    const CREATED_AT = 'createdAt';

    const UPDATED_AT = 'updatedAt';

    protected $fillable = [
        'code',
        'description',
        'type',
        'value',
        'minOrderPHP',
        'maxUses',
        'usedCount',
        'oneUsePerAccount',
        'startsAt',
        'expiresAt',
        'isActive',
    ];

    protected function casts(): array
    {
        return [
            'type' => PromotionType::class,
            'value' => 'decimal:2',
            'minOrderPHP' => 'decimal:2',
            'maxUses' => 'integer',
            'usedCount' => 'integer',
            'oneUsePerAccount' => 'boolean',
            'startsAt' => 'datetime',
            'expiresAt' => 'datetime',
            'isActive' => 'boolean',
        ];
    }

    public function eligibleProducts(): HasMany
    {
        return $this->hasMany(PromotionProduct::class, 'promotionId');
    }
}
