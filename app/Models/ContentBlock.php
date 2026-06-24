<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ContentBlock extends Model
{
    protected $table = 'ContentBlock';

    public static $snakeAttributes = false;

    public $incrementing = false;

    protected $keyType = 'string';

    protected $primaryKey = 'key';

    const CREATED_AT = null;

    const UPDATED_AT = 'updatedAt';

    protected $fillable = [
        'key',
        'value',
    ];

    protected function casts(): array
    {
        return [
            'value' => 'array',
        ];
    }
}
