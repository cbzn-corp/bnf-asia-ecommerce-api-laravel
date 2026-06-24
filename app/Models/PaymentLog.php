<?php

namespace App\Models;

use App\Models\Concerns\HasCuid;
use Illuminate\Database\Eloquent\Model;

class PaymentLog extends Model
{
    use HasCuid;

    protected $table = 'PaymentLog';

    public static $snakeAttributes = false;

    public $incrementing = false;

    protected $keyType = 'string';

    const CREATED_AT = 'createdAt';

    const UPDATED_AT = null;

    protected $fillable = [
        'provider',
        'orderNumber',
        'payload',
        'signatureValid',
    ];

    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'signatureValid' => 'boolean',
        ];
    }
}
