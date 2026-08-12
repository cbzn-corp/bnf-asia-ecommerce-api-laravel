<?php

namespace App\Http\Controllers;

use App\Services\WebhooksService;
use App\Support\Config\AppSecrets;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Exception\BadRequestException;

class WebhooksController extends Controller
{
    public function __construct(
        private readonly WebhooksService $webhooksService,
    ) {}

    public function handlePaymongo(Request $request)
    {
        return $this->handle('paymongo', $request, $request->header('Paymongo-Signature') ?? $request->header('paymongo-signature'));
    }

    public function handleStripe(Request $request)
    {
        return $this->handle('stripe', $request, $request->header('stripe-signature'));
    }

    private function handle(string $provider, Request $request, ?string $signature)
    {
        $secret = $provider === 'paymongo'
            ? AppSecrets::getPaymongoWebhookSecret()
            : AppSecrets::getStripeWebhookSecret();

        // Must use raw body bytes for HMAC — do not re-encode parsed JSON.
        $payload = $request->getContent();
        if ($payload === '') {
            $payload = json_encode($request->all()) ?: '';
        }

        $body = $request->all();

        $valid = false;
        if ($signature && $secret) {
            $valid = $provider === 'paymongo'
                ? $this->webhooksService->verifyPaymongoSignature($payload, $signature, $secret)
                : $this->webhooksService->verifySignature($payload, $signature, $secret);
        }

        $orderNumber = null;
        $paymongoPaymentType = null;
        $eventType = null;

        if ($provider === 'paymongo') {
            $details = $this->webhooksService->extractPaymongoPaymentDetails($body, $payload);
            $orderNumber = $details['orderNumber'];
            $paymongoPaymentType = $details['paymongoPaymentType'];
            $eventType = $details['eventType'];
        } else {
            $orderNumber = is_string($body['orderNumber'] ?? null) ? $body['orderNumber'] : null;
            $orderNumber = $orderNumber
                ?? (is_string(data_get($body, 'data.object.metadata.orderNumber'))
                    ? data_get($body, 'data.object.metadata.orderNumber')
                    : null);
        }

        $this->webhooksService->logPayment(
            $provider,
            $orderNumber,
            $payload,
            $valid,
        );

        if (! $valid) {
            Log::warning("Invalid {$provider} webhook signature", [
                'hasSignature' => (bool) $signature,
                'hasSecret' => (bool) $secret,
                'eventType' => $eventType,
                'orderNumber' => $orderNumber,
            ]);
            throw new BadRequestException("Invalid {$provider} webhook signature");
        }

        // Ignore non-paid events (still 200 so PayMongo stops retrying).
        if ($provider === 'paymongo' && $eventType !== null && ! $this->isPaymongoPaidEvent($eventType)) {
            return response()->json([
                'ok' => true,
                'ignored' => true,
                'eventType' => $eventType,
            ]);
        }

        if ($orderNumber === null || $orderNumber === '') {
            Log::warning("{$provider} webhook missing orderNumber", [
                'eventType' => $eventType,
            ]);
            throw new BadRequestException('orderNumber is required');
        }

        return response()->json(
            $this->webhooksService->markPaid($orderNumber, $paymongoPaymentType),
        );
    }

    private function isPaymongoPaidEvent(string $eventType): bool
    {
        return in_array($eventType, [
            'checkout_session.payment.paid',
            'payment.paid',
        ], true);
    }
}
