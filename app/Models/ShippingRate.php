<?php

namespace App\Models;

use App\Models\Concerns\HasCuid;
use Illuminate\Database\Eloquent\Model;

class ShippingRate extends Model
{
    use HasCuid;

    protected $table = 'ShippingRate';

    public static $snakeAttributes = false;

    public $incrementing = false;

    protected $keyType = 'string';

    const CREATED_AT = 'createdAt';

    const UPDATED_AT = 'updatedAt';

    protected $fillable = [
        'label',
        'region',
        'zone',
        'feeInPHP',
        'estimatedDays',
        'minWeightGrams',
        'maxWeightGrams',
        'isActive',
        'sortOrder',
    ];

    protected function casts(): array
    {
        return [
            'feeInPHP' => 'decimal:2',
            'minWeightGrams' => 'float',
            'maxWeightGrams' => 'float',
            'isActive' => 'boolean',
            'sortOrder' => 'integer',
        ];
    }
}
