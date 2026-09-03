<?php

namespace App\Models;

use App\Models\Concerns\HasCuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CartAddEvent extends Model
{
    use HasCuid;

    protected $table = 'CartAddEvent';

    public static $snakeAttributes = false;

    public $incrementing = false;

    protected $keyType = 'string';

    const CREATED_AT = 'createdAt';

    const UPDATED_AT = null;

    protected $fillable = [
        'userId',
        'productId',
        'variantId',
        'productName',
        'variantName',
        'slug',
        'qty',
        'priceInPHP',
    ];

    protected function casts(): array
    {
        return [
            'qty' => 'integer',
            'priceInPHP' => 'decimal:2',
            'createdAt' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'userId');
    }
}
