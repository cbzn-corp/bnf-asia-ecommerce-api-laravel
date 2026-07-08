<?php

namespace App\Models;

use App\Models\Concerns\HasCuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ReferralPartner extends Model
{
    use HasCuid;

    protected $table = 'ReferralPartner';

    public static $snakeAttributes = false;

    public $incrementing = false;

    protected $keyType = 'string';

    const CREATED_AT = 'createdAt';

    const UPDATED_AT = 'updatedAt';

    protected $fillable = [
        'name',
        'code',
        'email',
        'commissionRate',
        'isActive',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'commissionRate' => 'decimal:2',
            'isActive' => 'boolean',
        ];
    }

    public function products(): HasMany
    {
        return $this->hasMany(ReferralPartnerProduct::class, 'partnerId');
    }

    public function clicks(): HasMany
    {
        return $this->hasMany(ReferralClick::class, 'partnerId');
    }

    public function commissions(): HasMany
    {
        return $this->hasMany(ReferralCommission::class, 'partnerId');
    }
}
