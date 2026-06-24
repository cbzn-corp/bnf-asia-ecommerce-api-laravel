<?php

namespace App\Models;

use App\Models\Concerns\HasCuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class AbandonedCart extends Model
{
    use HasCuid;

    protected $table = 'AbandonedCart';

    public static $snakeAttributes = false;

    public $incrementing = false;

    protected $keyType = 'string';

    const CREATED_AT = 'createdAt';

    const UPDATED_AT = 'updatedAt';

    protected $fillable = [
        'email',
        'userId',
        'items',
        'recoveryToken',
        'lastActivityAt',
        'recoveryEmailSentAt',
        'recoveredAt',
    ];

    protected function casts(): array
    {
        return [
            'items' => 'array',
            'lastActivityAt' => 'datetime',
            'recoveryEmailSentAt' => 'datetime',
            'recoveredAt' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $model) {
            if (empty($model->recoveryToken)) {
                $model->recoveryToken = (string) Str::ulid();
            }
        });
    }
}
