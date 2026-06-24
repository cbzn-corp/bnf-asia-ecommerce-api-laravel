<?php

declare(strict_types=1);

namespace App\Support\Utils;

use Barryvdh\DomPDF\Facade\Pdf;

final class InvoicePdf
{
    private const PAYMENT_LABELS = [
        'PAYMONGO_GCASH' => 'GCash',
        'PAYMONGO_MAYA' => 'Maya',
        'STRIPE_CARD' => 'Credit / Debit Card',
        'COD' => 'Cash on Delivery',
        'BANK_TRANSFER' => 'Bank Transfer',
        'BNPL_INSTALLMENT' => 'Installment / BNPL',
        'SUPPORT_ASSISTED' => 'Support-assisted',
    ];

    /**
     * @param  array{
     *     storeName: string,
     *     storeEmail?: string|null,
     *     storePhone?: string|null,
     *     storeAddress?: string|null,
     *     orderNumber: string,
     *     createdAt: \DateTimeInterface,
     *     guestEmail: string,
     *     paymentMethod: string,
     *     paymentStatus: string,
     *     currency: string,
     *     totalDisplay: float,
     *     subtotalInPHP: float,
     *     discountAmountInPHP: float,
     *     taxAmountInPHP: float,
     *     shippingFeeInPHP: float,
     *     installationFeeInPHP: float,
     *     shippingAddressLines?: list<string>|null,
     *     orderItems: list<array{productName: string, variantLabel?: string|null, quantity: int, totalPriceInPHP: float}>,
     * }  $data
     */
    public static function generate(array $data): string
    {
        $paymentMethod = $data['paymentMethod'];
        $paymentLabel = self::PAYMENT_LABELS[$paymentMethod]
            ?? ucwords(strtolower(str_replace('_', ' ', $paymentMethod)));

        $orderItems = array_map(static function (array $item) {
            return [
                'productName' => $item['productName'],
                'variantLabel' => $item['variantLabel'] ?? null,
                'quantity' => $item['quantity'],
                'lineTotal' => self::formatMoney($item['totalPriceInPHP'], 'PHP'),
            ];
        }, $data['orderItems']);

        $html = view('invoice', [
            'storeName' => $data['storeName'],
            'storeEmail' => $data['storeEmail'] ?? null,
            'storePhone' => $data['storePhone'] ?? null,
            'storeAddress' => $data['storeAddress'] ?? null,
            'orderNumber' => $data['orderNumber'],
            'createdAt' => $data['createdAt']->format('M j, Y'),
            'guestEmail' => $data['guestEmail'],
            'paymentMethodLabel' => $paymentLabel,
            'paymentStatus' => $data['paymentStatus'],
            'orderItems' => $orderItems,
            'subtotal' => self::formatMoney($data['subtotalInPHP'], 'PHP'),
            'discount' => self::formatMoney($data['discountAmountInPHP'], 'PHP'),
            'tax' => self::formatMoney($data['taxAmountInPHP'], 'PHP'),
            'shipping' => self::formatMoney($data['shippingFeeInPHP'], 'PHP'),
            'installation' => self::formatMoney($data['installationFeeInPHP'], 'PHP'),
            'grandTotal' => self::formatMoney($data['totalDisplay'], $data['currency']),
            'shippingAddressLines' => $data['shippingAddressLines'] ?? null,
        ])->render();

        return Pdf::loadHTML($html)->setPaper('a4')->output();
    }

    private static function formatMoney(float $amount, string $currency): string
    {
        if ($currency === 'USD') {
            return '$'.number_format($amount, 2, '.', ',');
        }

        return '₱'.number_format($amount, 2, '.', ',');
    }
}
