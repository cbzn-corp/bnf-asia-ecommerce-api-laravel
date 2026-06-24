<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CollectionProduct extends Model
{
    protected $table = 'CollectionProduct';

    public static $snakeAttributes = false;

    public $incrementing = false;

    public $timestamps = false;

    protected $fillable = [
        'collectionId',
        'productId',
        'sortOrder',
    ];

    protected function casts(): array
    {
        return [
            'sortOrder' => 'integer',
        ];
    }

    public function collection(): BelongsTo
    {
        return $this->belongsTo(Collection::class, 'collectionId');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'productId');
    }
}
