<?php

namespace App\Models;

use App\Models\Concerns\HasCuid;
use Illuminate\Database\Eloquent\Model;

class PickupLocation extends Model
{
    use HasCuid;

    protected $table = 'PickupLocation';

    public static $snakeAttributes = false;

    public $incrementing = false;

    protected $keyType = 'string';

    const CREATED_AT = 'createdAt';

    const UPDATED_AT = 'updatedAt';

    protected $fillable = [
        'name',
        'address',
        'city',
        'province',
        'phone',
        'isActive',
        'sortOrder',
    ];

    protected function casts(): array
    {
        return [
            'isActive' => 'boolean',
            'sortOrder' => 'integer',
        ];
    }
}
