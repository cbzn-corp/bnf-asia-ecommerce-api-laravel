<?php

namespace App\Models;

use App\Casts\PostgresTextArray;
use App\Models\Concerns\HasCuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Role extends Model
{
    use HasCuid;

    protected $table = 'Role';

    public static $snakeAttributes = false;

    public $incrementing = false;

    protected $keyType = 'string';

    const CREATED_AT = 'createdAt';

    const UPDATED_AT = 'updatedAt';

    protected $fillable = [
        'key',
        'name',
        'description',
        'isSystem',
        'isStaff',
        'permissions',
    ];

    protected function casts(): array
    {
        return [
            'isSystem' => 'boolean',
            'isStaff' => 'boolean',
            'permissions' => PostgresTextArray::class,
        ];
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class, 'roleId');
    }
}
