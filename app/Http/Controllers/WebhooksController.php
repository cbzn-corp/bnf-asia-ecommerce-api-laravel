<?php

namespace App\Http\Controllers;

use App\Services\WebhooksService;
use App\Support\Config\AppSecrets;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Exception\BadRequestException;

class WebhooksController extends Controller
{
    public function __construct(
        private readonly WebhooksService $webhooksService,
    ) {}

    public function handlePaymongo(Request $request)
    {
        return $this->handle('paymongo', $request, $request->header('paymongo-signature'));
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

        $body = $request->all();
        $payload = $body['rawPayload'] ?? $request->getContent();
        if ($payload === '' || $payload === []) {
            $payload = json_encode($body) ?: '';
        }
        if (is_array($payload)) {
            $payload = json_encode($payload) ?: '';
        }

        $valid = (bool) ($signature && $secret && $this->webhooksService->verifySignature($payload, $signature, $secret));

        $orderNumber = null;
        $paymongoPaymentType = null;
        if ($provider === 'paymongo') {
            $details = $this->webhooksService->extractPaymongoPaymentDetails($body, $payload);
            $orderNumber = $details['orderNumber'];
            $paymongoPaymentType = $details['paymongoPaymentType'];
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
            throw new BadRequestException("Invalid {$provider} webhook signature");
        }

        return response()->json(
            $this->webhooksService->markPaid($orderNumber ?? '', $paymongoPaymentType),
        );
    }
}
