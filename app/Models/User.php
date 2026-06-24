<?php

namespace App\Models;

use App\Config\Permissions;
use App\Models\Concerns\HasCuid;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use PHPOpenSourceSaver\JWTAuth\Contracts\JWTSubject;

class User extends Authenticatable implements JWTSubject
{
    use HasCuid, Notifiable;

    protected $table = 'User';

    public static $snakeAttributes = false;

    public $incrementing = false;

    protected $keyType = 'string';

    const CREATED_AT = 'createdAt';

    const UPDATED_AT = 'updatedAt';

    protected $fillable = [
        'email',
        'passwordHash',
        'roleId',
        'isActive',
        'marketingOptIn',
    ];

    protected $hidden = [
        'passwordHash',
    ];

    protected function casts(): array
    {
        return [
            'isActive' => 'boolean',
            'marketingOptIn' => 'boolean',
        ];
    }

    public function getAuthPassword(): string
    {
        return $this->passwordHash;
    }

    public function getJWTIdentifier(): mixed
    {
        return $this->getKey();
    }

    /**
     * @return array<string, mixed>
     */
    public function getJWTCustomClaims(): array
    {
        $this->loadMissing('role');

        return [
            'id' => $this->id,
            'email' => $this->email,
            'roleId' => $this->roleId,
            'roleKey' => $this->role?->key,
            'roleName' => $this->role?->name,
            'isStaff' => (bool) $this->role?->isStaff,
            'permissions' => Permissions::sanitizePermissions(array_values($this->role?->permissions ?? [])),
        ];
    }

    public function role(): BelongsTo
    {
        return $this->belongsTo(Role::class, 'roleId');
    }

    public function addresses(): HasMany
    {
        return $this->hasMany(Address::class, 'userId');
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class, 'userId');
    }

    public function reviews(): HasMany
    {
        return $this->hasMany(ProductReview::class, 'userId');
    }

    public function wishlistItems(): HasMany
    {
        return $this->hasMany(WishlistItem::class, 'userId');
    }

    public function orderRequests(): HasMany
    {
        return $this->hasMany(OrderRequest::class, 'userId');
    }

    public function stockAlerts(): HasMany
    {
        return $this->hasMany(StockAlert::class, 'userId');
    }
}
