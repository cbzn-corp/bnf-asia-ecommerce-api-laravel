<?php

namespace App\Models;

use App\Enums\Currency;
use App\Enums\DeliveryMethod;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Enums\QuoteStatus;
use App\Enums\RefundStatus;
use App\Enums\ShippingStatus;
use App\Models\Concerns\HasCuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Order extends Model
{
    use HasCuid;

    protected $table = 'Order';

    public static $snakeAttributes = false;

    public $incrementing = false;

    protected $keyType = 'string';

    const CREATED_AT = 'createdAt';

    const UPDATED_AT = 'updatedAt';

    protected $fillable = [
        'orderNumber',
        'userId',
        'guestEmail',
        'guestPhone',
        'exchangeRate',
        'subtotalInPHP',
        'taxAmountInPHP',
        'discountAmountInPHP',
        'shippingFeeInPHP',
        'shippingZone',
        'shippingRateId',
        'installationFeeInPHP',
        'installationRequested',
        'totalAmountInPHP',
        'promotionCode',
        'referralPartnerId',
        'referralCode',
        'paymentSessionId',
        'paymentSessionUrl',
        'pickupLocationId',
        'trackingNumber',
        'carrier',
        'estimatedDeliveryAt',
        'shippingAddress',
        'customerNote',
        'quoteStaleAt',
        'refundAmountInPHP',
        'refundReason',
        'currency',
        'paymentMethod',
        'paymentStatus',
        'shippingStatus',
        'refundStatus',
        'deliveryMethod',
        'quoteStatus',
        'deliveryFeeDeferred',
    ];

    protected function casts(): array
    {
        return [
            'exchangeRate' => 'decimal:6',
            'subtotalInPHP' => 'decimal:2',
            'taxAmountInPHP' => 'decimal:2',
            'discountAmountInPHP' => 'decimal:2',
            'shippingFeeInPHP' => 'decimal:2',
            'installationFeeInPHP' => 'decimal:2',
            'installationRequested' => 'boolean',
            'deliveryFeeDeferred' => 'boolean',
            'totalAmountInPHP' => 'decimal:2',
            'estimatedDeliveryAt' => 'datetime',
            'shippingAddress' => 'array',
            'quoteStaleAt' => 'datetime',
            'refundAmountInPHP' => 'decimal:2',
            'currency' => Currency::class,
            'paymentMethod' => PaymentMethod::class,
            'paymentStatus' => PaymentStatus::class,
            'shippingStatus' => ShippingStatus::class,
            'refundStatus' => RefundStatus::class,
            'deliveryMethod' => DeliveryMethod::class,
            'quoteStatus' => QuoteStatus::class,
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'userId');
    }

    public function orderItems(): HasMany
    {
        return $this->hasMany(OrderItem::class, 'orderId');
    }

    public function supportConversation(): HasOne
    {
        return $this->hasOne(SupportConversation::class, 'orderId');
    }

    public function statusHistory(): HasMany
    {
        return $this->hasMany(OrderStatusHistory::class, 'orderId');
    }

    public function notes(): HasMany
    {
        return $this->hasMany(OrderNote::class, 'orderId');
    }

    public function orderRequests(): HasMany
    {
        return $this->hasMany(OrderRequest::class, 'orderId');
    }

    public function shipmentEvents(): HasMany
    {
        return $this->hasMany(ShipmentEvent::class, 'orderId');
    }

    public function referralPartner(): BelongsTo
    {
        return $this->belongsTo(ReferralPartner::class, 'referralPartnerId');
    }
}
