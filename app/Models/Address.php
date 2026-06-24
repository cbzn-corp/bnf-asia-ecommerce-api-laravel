<?php

namespace App\Models;

use App\Models\Concerns\HasCuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Address extends Model
{
    use HasCuid;

    protected $table = 'Address';

    public static $snakeAttributes = false;

    public $incrementing = false;

    protected $keyType = 'string';

    const CREATED_AT = 'createdAt';

    const UPDATED_AT = 'updatedAt';

    protected $fillable = [
        'userId',
        'label',
        'country',
        'street1',
        'street2',
        'region',
        'province',
        'city',
        'barangay',
        'postalCode',
        'isDefault',
    ];

    protected function casts(): array
    {
        return [
            'isDefault' => 'boolean',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'userId');
    }
}
