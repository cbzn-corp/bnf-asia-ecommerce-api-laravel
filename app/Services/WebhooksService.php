<?php

namespace App\Services;

use App\Enums\PaymentStatus;
use App\Models\Order;
use App\Models\PaymentLog;
use Symfony\Component\HttpFoundation\Exception\BadRequestException;

class WebhooksService
{
    public function verifySignature(string $payload, string $signature, string $secret): bool
    {
        $expected = hash_hmac('sha256', $payload, $secret);

        return hash_equals($expected, $signature);
    }

    public function logPayment(string $provider, ?string $orderNumber, string $payload, bool $signatureValid): PaymentLog
    {
        $parsed = json_decode($payload, true);
        if (! is_array($parsed)) {
            $parsed = ['raw' => $payload];
        }

        return PaymentLog::query()->create([
            'provider' => $provider,
            'orderNumber' => $orderNumber,
            'payload' => $parsed,
            'signatureValid' => $signatureValid,
        ]);
    }

    public function markPaid(string $orderNumber): Order
    {
        if ($orderNumber === '') {
            throw new BadRequestException('orderNumber is required');
        }

        $order = Order::query()->where('orderNumber', $orderNumber)->firstOrFail();
        $order->update(['paymentStatus' => PaymentStatus::Paid]);

        return $order->fresh();
    }
}
