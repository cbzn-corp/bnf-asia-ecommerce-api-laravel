<?php

namespace App\Services;

use App\Enums\PaymentMethod;
use App\Services\Settings\PlatformSettingsService;
use App\Support\Config\AppSecrets;
use App\Support\Config\AppUrls;
use App\Support\Utils\Money;
use App\Support\Utils\PaymentMethods;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class PaymentsService
{
    public function __construct(
        private readonly PlatformSettingsService $platformSettings,
    ) {}

    /**
     * @param  array{
     *     orderNumber: string,
     *     orderId: string,
     *     paymentMethod: PaymentMethod|string,
     *     totalAmountInPHP: string|float,
     *     exchangeRate: string|float,
     *     currency: string,
     *     customerEmail: string,
     * }  $params
     * @return array{configured: bool, paymentSessionId: string|null, paymentSessionUrl: string|null, message?: string}
     */
    public function createPaymentSession(array $params): array
    {
        $method = $params['paymentMethod'] instanceof PaymentMethod
            ? $params['paymentMethod']->value
            : $params['paymentMethod'];

        if ($method === PaymentMethod::BnplInstallment->value) {
            return [
                'configured' => true,
                'paymentSessionId' => null,
                'paymentSessionUrl' => null,
                'message' => 'BNPL installment selected — our team will contact you to complete financing.',
            ];
        }

        if ($method === PaymentMethod::Cod->value) {
            return ['configured' => true, 'paymentSessionId' => null, 'paymentSessionUrl' => null];
        }

        if ($method === PaymentMethod::BankTransfer->value) {
            return [
                'configured' => true,
                'paymentSessionId' => null,
                'paymentSessionUrl' => null,
                'message' => 'Bank transfer selected — we will email payment instructions after your order is confirmed.',
            ];
        }

        if ($method === PaymentMethod::SupportAssisted->value) {
            return [
                'configured' => true,
                'paymentSessionId' => null,
                'paymentSessionUrl' => null,
                'message' => 'Our team is reviewing your order. Open your account to chat with us and finalize delivery and payment.',
            ];
        }

        $settings = $this->platformSettings->getRaw();
        $baseUrl = AppUrls::getStorefrontUrl();
        $emailQuery = rawurlencode($params['customerEmail']);
        $successUrl = "{$baseUrl}/order-confirmation?order={$params['orderNumber']}&email={$emailQuery}";
        $cancelUrl = "{$baseUrl}/order-confirmation?order={$params['orderNumber']}&email={$emailQuery}&payment=cancelled";

        if ($method === PaymentMethod::StripeCard->value) {
            $stripeSecretKey = AppSecrets::getStripeSecretKey();
            if (! $settings->stripeEnabled || ! $stripeSecretKey) {
                return [
                    'configured' => false,
                    'paymentSessionId' => null,
                    'paymentSessionUrl' => null,
                    'message' => 'Stripe is not configured. Set STRIPE_SECRET_KEY in the API environment and enable Stripe here.',
                ];
            }

            return $this->createStripeSession([
                'secretKey' => $stripeSecretKey,
                'orderNumber' => $params['orderNumber'],
                'amountUsd' => (float) Money::toUsdFromPhp($params['totalAmountInPHP'], $params['exchangeRate']),
                'customerEmail' => $params['customerEmail'],
                'successUrl' => $successUrl,
                'cancelUrl' => $cancelUrl,
            ]);
        }

        $paymongoSecretKey = AppSecrets::getPaymongoSecretKey();
        if (! $settings->paymongoEnabled || ! $paymongoSecretKey) {
            return [
                'configured' => false,
                'paymentSessionId' => null,
                'paymentSessionUrl' => null,
                'message' => 'PayMongo is not configured. Set PAYMONGO_SECRET_KEY in the API environment and enable PayMongo here.',
            ];
        }

        $paymentMethodEnum = $params['paymentMethod'] instanceof PaymentMethod
            ? $params['paymentMethod']
            : PaymentMethod::tryFrom((string) $method);

        if (! $paymentMethodEnum?->isPaymongo()) {
            return [
                'configured' => false,
                'paymentSessionId' => null,
                'paymentSessionUrl' => null,
                'message' => 'Unsupported payment method for PayMongo.',
            ];
        }

        $paymentMethodTypes = PaymentMethods::resolvePaymongoPaymentMethodTypes([
            'paymongoPaymentMethodTypes' => $settings->paymongoPaymentMethodTypes,
        ]);

        // Legacy single-channel enums still pin to one type when staff uses them.
        if ($paymentMethodEnum === PaymentMethod::PaymongoGcash) {
            $paymentMethodTypes = ['gcash', 'grab_pay'];
        } elseif ($paymentMethodEnum === PaymentMethod::PaymongoMaya) {
            $paymentMethodTypes = ['paymaya'];
        }

        if ($paymentMethodTypes === []) {
            return [
                'configured' => false,
                'paymentSessionId' => null,
                'paymentSessionUrl' => null,
                'message' => 'No PayMongo payment channels are enabled. Configure them under Settings → Payments.',
            ];
        }

        return $this->createPayMongoSession([
            'secretKey' => $paymongoSecretKey,
            'orderNumber' => $params['orderNumber'],
            'amountPHP' => (float) $params['totalAmountInPHP'],
            'paymentMethodTypes' => $paymentMethodTypes,
            'customerEmail' => $params['customerEmail'],
            'successUrl' => $successUrl,
            'cancelUrl' => $cancelUrl,
        ]);
    }

    /**
     * @param  array{secretKey: string, orderNumber: string, amountUsd: float, customerEmail: string, successUrl: string, cancelUrl: string}  $params
     * @return array{configured: bool, paymentSessionId: string|null, paymentSessionUrl: string|null, message?: string}
     */
    private function createStripeSession(array $params): array
    {
        try {
            $response = Http::asForm()
                ->withToken($params['secretKey'])
                ->post('https://api.stripe.com/v1/checkout/sessions', [
                    'mode' => 'payment',
                    'success_url' => $params['successUrl'],
                    'cancel_url' => $params['cancelUrl'],
                    'customer_email' => $params['customerEmail'],
                    'line_items[0][price_data][currency]' => 'usd',
                    'line_items[0][price_data][product_data][name]' => "Order {$params['orderNumber']}",
                    'line_items[0][price_data][unit_amount]' => (string) (int) round($params['amountUsd'] * 100),
                    'line_items[0][quantity]' => '1',
                    'metadata[orderNumber]' => $params['orderNumber'],
                ]);

            $data = $response->json();
            if (! $response->successful()) {
                $message = $data['error']['message'] ?? $response->reason();
                Log::error("Stripe session failed: {$message}");

                return [
                    'configured' => true,
                    'paymentSessionId' => null,
                    'paymentSessionUrl' => null,
                    'message' => $message,
                ];
            }

            return [
                'configured' => true,
                'paymentSessionId' => $data['id'] ?? null,
                'paymentSessionUrl' => $data['url'] ?? null,
            ];
        } catch (\Throwable $err) {
            Log::error('Stripe API error: '.$err->getMessage());

            return [
                'configured' => true,
                'paymentSessionId' => null,
                'paymentSessionUrl' => null,
                'message' => 'Could not reach Stripe',
            ];
        }
    }

    /**
     * @param  array{secretKey: string, orderNumber: string, amountPHP: float, paymentMethodTypes: list<string>, customerEmail: string, successUrl: string, cancelUrl: string}  $params
     * @return array{configured: bool, paymentSessionId: string|null, paymentSessionUrl: string|null, message?: string}
     */
    private function createPayMongoSession(array $params): array
    {
        $payload = [
            'data' => [
                'attributes' => [
                    'billing' => ['email' => $params['customerEmail']],
                    'send_email_receipt' => true,
                    'show_description' => true,
                    'show_line_items' => true,
                    'description' => "BNF Asia order {$params['orderNumber']}",
                    'line_items' => [[
                        'amount' => (int) round($params['amountPHP'] * 100),
                        'currency' => 'PHP',
                        'name' => "Order {$params['orderNumber']}",
                        'quantity' => 1,
                    ]],
                    'payment_method_types' => $params['paymentMethodTypes'],
                    'success_url' => $params['successUrl'],
                    'cancel_url' => $params['cancelUrl'],
                    'reference_number' => $params['orderNumber'],
                    'metadata' => ['orderNumber' => $params['orderNumber']],
                ],
            ],
        ];

        try {
            $response = Http::withBasicAuth($params['secretKey'], '')
                ->acceptJson()
                ->post('https://api.paymongo.com/v1/checkout_sessions', $payload);

            $json = $response->json();
            if (! $response->successful()) {
                $msg = $json['errors'][0]['detail'] ?? 'PayMongo checkout failed';
                Log::error("PayMongo session failed: {$msg}");

                return ['configured' => true, 'paymentSessionId' => null, 'paymentSessionUrl' => null, 'message' => $msg];
            }

            return [
                'configured' => true,
                'paymentSessionId' => $json['data']['id'] ?? null,
                'paymentSessionUrl' => $json['data']['attributes']['checkout_url'] ?? null,
            ];
        } catch (\Throwable $err) {
            Log::error('PayMongo API error: '.$err->getMessage());

            return [
                'configured' => true,
                'paymentSessionId' => null,
                'paymentSessionUrl' => null,
                'message' => 'Could not reach PayMongo',
            ];
        }
    }
}
