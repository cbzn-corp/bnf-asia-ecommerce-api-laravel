<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PlatformSetting extends Model
{
    protected $table = 'PlatformSetting';

    public static $snakeAttributes = false;

    public $incrementing = false;

    protected $keyType = 'string';

    const UPDATED_AT = 'updatedAt';

    const CREATED_AT = null;

    protected $fillable = [
        'id',
        'phpPerUsd',
        'freeShippingEnabled',
        'freeShippingMinPHP',
        'vatRatePercent',
        'vatEnabled',
        'paymongoPublicKey',
        'paymongoEnabled',
        'stripePublishableKey',
        'stripeEnabled',
        'lowStockThreshold',
        'bnplEnabled',
        'abandonedCartEnabled',
        'abandonedCartHours',
        'supportAssistedCheckoutEnabled',
        'customerChatEnabled',
        'quoteStaleAlertDays',
        'storeName',
        'storeEmail',
        'storePhone',
        'storeAddress',
        'checkoutOrderNotesEnabled',
        'guestCheckoutEnabled',
        'compareEnabled',
        'codEnabled',
        'bankTransferEnabled',
        'paymongoGcashEnabled',
        'paymongoMayaEnabled',
        'pricesIncludeVat',
        'abandonedCartDiscountCode',
        'maintenanceModeEnabled',
        'maintenanceMessage',
        'maintenanceWhitelistIps',
        'maintenanceBypassSecret',
        'deliveryFeeAtCheckoutEnabled',
    ];

    protected function casts(): array
    {
        return [
            'phpPerUsd' => 'decimal:4',
            'freeShippingEnabled' => 'boolean',
            'freeShippingMinPHP' => 'decimal:2',
            'vatRatePercent' => 'decimal:2',
            'vatEnabled' => 'boolean',
            'paymongoEnabled' => 'boolean',
            'stripeEnabled' => 'boolean',
            'lowStockThreshold' => 'integer',
            'bnplEnabled' => 'boolean',
            'abandonedCartEnabled' => 'boolean',
            'abandonedCartHours' => 'integer',
            'supportAssistedCheckoutEnabled' => 'boolean',
            'customerChatEnabled' => 'boolean',
            'quoteStaleAlertDays' => 'integer',
            'checkoutOrderNotesEnabled' => 'boolean',
            'guestCheckoutEnabled' => 'boolean',
            'compareEnabled' => 'boolean',
            'codEnabled' => 'boolean',
            'bankTransferEnabled' => 'boolean',
            'paymongoGcashEnabled' => 'boolean',
            'paymongoMayaEnabled' => 'boolean',
            'pricesIncludeVat' => 'boolean',
            'maintenanceModeEnabled' => 'boolean',
            'deliveryFeeAtCheckoutEnabled' => 'boolean',
        ];
    }
}
