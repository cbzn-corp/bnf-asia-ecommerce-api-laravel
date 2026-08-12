<?php

namespace App\Services;

use App\Enums\PaymentStatus;
use App\Models\Order;
use App\Models\PaymentLog;
use Symfony\Component\HttpFoundation\Exception\BadRequestException;

class WebhooksService
{
    public function __construct(
        private readonly ReferralsService $referralsService,
    ) {}

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

    /**
     * @return array{orderNumber: string|null, paymongoPaymentType: string|null}
     */
    public function extractPaymongoPaymentDetails(array $body, string $rawPayload): array
    {
        $parsed = json_decode($rawPayload, true);
        if (! is_array($parsed)) {
            $parsed = $body;
        }

        $orderNumber = $this->firstNonEmptyString([
            $body['orderNumber'] ?? null,
            data_get($parsed, 'data.attributes.metadata.orderNumber'),
            data_get($parsed, 'data.attributes.reference_number'),
            data_get($parsed, 'data.data.attributes.metadata.orderNumber'),
            data_get($parsed, 'data.data.attributes.reference_number'),
            data_get($parsed, 'attributes.metadata.orderNumber'),
            data_get($parsed, 'attributes.reference_number'),
        ]);

        $paymongoPaymentType = $this->firstNonEmptyString([
            data_get($parsed, 'data.attributes.payments.0.attributes.source.type'),
            data_get($parsed, 'data.data.attributes.payments.0.attributes.source.type'),
            data_get($parsed, 'attributes.payments.0.attributes.source.type'),
            data_get($parsed, 'data.attributes.source.type'),
            data_get($parsed, 'data.data.attributes.source.type'),
            data_get($parsed, 'data.attributes.type'), // payment.paid resource sometimes
        ]);

        // payment.paid events nest source under data.attributes
        if ($paymongoPaymentType === null) {
            $sourceType = data_get($parsed, 'data.attributes.source.type')
                ?? data_get($parsed, 'data.data.attributes.source.type');
            if (is_string($sourceType) && $sourceType !== '') {
                $paymongoPaymentType = $sourceType;
            }
        }

        return [
            'orderNumber' => $orderNumber,
            'paymongoPaymentType' => $paymongoPaymentType !== null ? strtolower($paymongoPaymentType) : null,
        ];
    }

    /**
     * @param  list<mixed>  $candidates
     */
    private function firstNonEmptyString(array $candidates): ?string
    {
        foreach ($candidates as $candidate) {
            if (is_string($candidate) && trim($candidate) !== '') {
                return trim($candidate);
            }
        }

        return null;
    }

    public function markPaid(string $orderNumber, ?string $paymongoPaymentType = null): Order
    {
        if ($orderNumber === '') {
            throw new BadRequestException('orderNumber is required');
        }

        $order = Order::query()->where('orderNumber', $orderNumber)->firstOrFail();
        $update = ['paymentStatus' => PaymentStatus::Paid];
        if ($paymongoPaymentType !== null && $paymongoPaymentType !== '') {
            $update['paymongoPaymentType'] = $paymongoPaymentType;
        }
        $order->update($update);
        $order = $order->fresh();

        $this->referralsService->onOrderPaid($order);

        return $order;
    }
}
