<?php

namespace App\Models;

use App\Enums\CollectionType;
use App\Models\Concerns\HasCuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Collection extends Model
{
    use HasCuid;

    protected $table = 'Collection';

    public static $snakeAttributes = false;

    public $incrementing = false;

    protected $keyType = 'string';

    const CREATED_AT = 'createdAt';

    const UPDATED_AT = 'updatedAt';

    protected $fillable = [
        'name',
        'slug',
        'description',
        'type',
        'rules',
        'imageUrl',
        'sortOrder',
        'isActive',
    ];

    protected function casts(): array
    {
        return [
            'type' => CollectionType::class,
            'rules' => 'array',
            'sortOrder' => 'integer',
            'isActive' => 'boolean',
        ];
    }

    public function products(): HasMany
    {
        return $this->hasMany(CollectionProduct::class, 'collectionId');
    }
}
