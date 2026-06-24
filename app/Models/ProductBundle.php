<?php

namespace App\Models;

use App\Models\Concerns\HasCuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ProductBundle extends Model
{
    use HasCuid;

    protected $table = 'ProductBundle';

    public static $snakeAttributes = false;

    public $incrementing = false;

    protected $keyType = 'string';

    const CREATED_AT = 'createdAt';

    const UPDATED_AT = 'updatedAt';

    protected $fillable = [
        'name',
        'slug',
        'description',
        'discountPercent',
        'imageUrl',
        'isActive',
    ];

    protected function casts(): array
    {
        return [
            'discountPercent' => 'decimal:2',
            'isActive' => 'boolean',
        ];
    }

    public function items(): HasMany
    {
        return $this->hasMany(BundleItem::class, 'bundleId');
    }
}
