<?php

namespace App\Services;

use App\Enums\PaymentStatus;
use App\Models\Order;
use App\Models\PaymentLog;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Exception\BadRequestException;

class WebhooksService
{
    public function __construct(
        private readonly ReferralsService $referralsService,
    ) {}

    /**
     * Legacy simple HMAC (used by older Stripe helper / tests). Prefer provider-specific verifiers.
     */
    public function verifySignature(string $payload, string $signature, string $secret): bool
    {
        $expected = hash_hmac('sha256', $payload, $secret);

        return hash_equals($expected, $signature);
    }

    /**
     * PayMongo signs: HMAC-SHA256("{t}.{rawBody}", webhookSecret)
     * Header: Paymongo-Signature: t=...,te=...,li=...
     * Compare against `te` (test) or `li` (live).
     */
    public function verifyPaymongoSignature(string $payload, string $signatureHeader, string $secret): bool
    {
        $parts = [];
        foreach (explode(',', $signatureHeader) as $segment) {
            $segment = trim($segment);
            if ($segment === '' || ! str_contains($segment, '=')) {
                continue;
            }
            [$key, $value] = explode('=', $segment, 2);
            $parts[trim($key)] = trim($value);
        }

        $timestamp = $parts['t'] ?? null;
        if ($timestamp === null || $timestamp === '') {
            return false;
        }

        $expected = hash_hmac('sha256', $timestamp.'.'.$payload, $secret);
        $candidates = array_values(array_filter([
            $parts['te'] ?? null,
            $parts['li'] ?? null,
        ], fn ($v) => is_string($v) && $v !== ''));

        foreach ($candidates as $candidate) {
            if (hash_equals($expected, $candidate)) {
                return true;
            }
        }

        return false;
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
     * @return array{orderNumber: string|null, paymongoPaymentType: string|null, eventType: string|null}
     */
    public function extractPaymongoPaymentDetails(array $body, string $rawPayload): array
    {
        $parsed = json_decode($rawPayload, true);
        if (! is_array($parsed)) {
            $parsed = $body;
        }

        // PayMongo event envelope: data.attributes.{type, data: resource}
        // Dashboard "resource" view may also look like data.attributes directly.
        $eventType = $this->firstNonEmptyString([
            data_get($parsed, 'data.attributes.type'),
            data_get($parsed, 'type'),
        ]);

        $resource = data_get($parsed, 'data.attributes.data');
        if (! is_array($resource)) {
            $resource = data_get($parsed, 'data');
        }
        if (! is_array($resource)) {
            $resource = $parsed;
        }

        $attrs = data_get($resource, 'attributes');
        if (! is_array($attrs)) {
            $attrs = is_array(data_get($parsed, 'data.attributes'))
                ? data_get($parsed, 'data.attributes')
                : [];
        }

        $orderNumber = $this->firstNonEmptyString([
            $body['orderNumber'] ?? null,
            data_get($attrs, 'metadata.orderNumber'),
            data_get($attrs, 'reference_number'),
            data_get($parsed, 'data.attributes.data.attributes.metadata.orderNumber'),
            data_get($parsed, 'data.attributes.data.attributes.reference_number'),
            data_get($parsed, 'data.attributes.metadata.orderNumber'),
            data_get($parsed, 'data.attributes.reference_number'),
            $this->orderNumberFromText(data_get($attrs, 'description')),
            $this->orderNumberFromText(data_get($attrs, 'statement_descriptor')),
        ]);

        $paymongoPaymentType = $this->firstNonEmptyString([
            data_get($attrs, 'payments.0.attributes.source.type'),
            data_get($attrs, 'source.type'),
            data_get($parsed, 'data.attributes.data.attributes.payments.0.attributes.source.type'),
            data_get($parsed, 'data.attributes.data.attributes.source.type'),
            data_get($parsed, 'data.attributes.payments.0.attributes.source.type'),
            data_get($parsed, 'data.attributes.source.type'),
        ]);

        return [
            'orderNumber' => $orderNumber,
            'paymongoPaymentType' => $paymongoPaymentType !== null ? strtolower($paymongoPaymentType) : null,
            'eventType' => $eventType,
        ];
    }

    private function orderNumberFromText(mixed $text): ?string
    {
        if (! is_string($text) || $text === '') {
            return null;
        }

        if (preg_match('/\b(ORD-[A-Za-z0-9-]+)\b/', $text, $matches) === 1) {
            return $matches[1];
        }

        return null;
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

        $order = Order::query()->where('orderNumber', $orderNumber)->first();
        if (! $order) {
            Log::warning("PayMongo webhook: order not found: {$orderNumber}");
            throw new BadRequestException("Order not found: {$orderNumber}");
        }

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
