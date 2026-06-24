<?php

namespace App\Models;

use App\Models\Concerns\HasCuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Category extends Model
{
    use HasCuid;

    protected $table = 'Category';

    public static $snakeAttributes = false;

    public $incrementing = false;

    protected $keyType = 'string';

    const CREATED_AT = 'createdAt';

    const UPDATED_AT = 'updatedAt';

    protected $fillable = [
        'name',
        'slug',
        'description',
        'imageUrl',
        'sortOrder',
        'parentId',
    ];

    protected function casts(): array
    {
        return [
            'sortOrder' => 'integer',
        ];
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(Category::class, 'parentId');
    }

    public function children(): HasMany
    {
        return $this->hasMany(Category::class, 'parentId');
    }

    public function products(): HasMany
    {
        return $this->hasMany(Product::class, 'categoryId');
    }
}
