<?php

namespace App\Services;

use App\Config\Permissions;
use App\Enums\Currency;
use App\Enums\DeliveryMethod;
use App\Enums\OrderRequestStatus;
use App\Enums\OrderRequestType;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Enums\QuoteStatus;
use App\Enums\RefundStatus;
use App\Enums\ShippingStatus;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OrderNote;
use App\Models\OrderRequest;
use App\Models\OrderStatusHistory;
use App\Models\Product;
use App\Models\ProductBundle;
use App\Models\ShipmentEvent;
use App\Models\ShippingRate;
use App\Services\Audit\AuditService;
use App\Services\Email\EmailService;
use App\Services\Settings\PlatformSettingsService;
use App\Support\Auth\AuthUser;
use App\Support\Config\AppUrls;
use App\Support\Utils\Decimal;
use App\Support\Utils\InvoicePdf;
use App\Support\Utils\Money;
use App\Support\Utils\PaymentMethods;
use App\Support\Utils\ShippingRegion;
use App\Support\Utils\ShippingWorkflow;
use App\Support\Utils\ShippingZone;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Exception\BadRequestException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class OrdersService
{
    public function __construct(
        private readonly EmailService $emailService,
        private readonly PromotionsService $promotionsService,
        private readonly PlatformSettingsService $platformSettings,
        private readonly PaymentsService $paymentsService,
        private readonly AuditService $auditService,
        private readonly AbandonedCartsService $abandonedCartsService,
        private readonly SupportChatService $supportChat,
    ) {}

    /**
     * @param  array<string, mixed>  $dto
     * @return array<string, mixed>
     */
    public function preview(array $dto): array
    {
        $pricing = $this->calculatePricing(
            $dto['items'],
            $dto['shippingAddress'] ?? null,
            $dto['promotionCode'] ?? null,
            isset($dto['deliveryMethod']) ? DeliveryMethod::from($dto['deliveryMethod']) : null,
            $dto['userId'] ?? null,
            $dto['bundleId'] ?? null,
            $dto['installationRequested'] ?? false,
            $dto['shippingRateId'] ?? null,
        );

        if (isset($dto['shippingFeeInPHP'])) {
            $shippingFee = Decimal::of($dto['shippingFeeInPHP']);
            $pricing['shippingFeeInPHP'] = $shippingFee;
            $pricing['totalInPHP'] = $this->recalculateOrderTotal([
                'subtotalInPHP' => $pricing['subtotalInPHP'],
                'discountAmountInPHP' => $pricing['discountAmountInPHP'],
                'taxAmountInPHP' => $pricing['taxAmountInPHP'],
                'shippingFeeInPHP' => $shippingFee,
                'installationFeeInPHP' => $pricing['installationFeeInPHP'],
            ]);
            $pricing['totalDisplay'] = $pricing['currency'] === Currency::USD
                ? (float) Money::toUsdFromPhp($pricing['totalInPHP'], $pricing['exchangeRate'])
                : Decimal::toFloat($pricing['totalInPHP']);
        }

        if (! empty($dto['forManualOrder'])) {
            $country = trim($dto['shippingAddress']['country'] ?? 'PH');
            $region = ShippingRegion::getShippingRegion($country);
            $settings = $this->platformSettings->getRaw();
            $pricing['availablePaymentMethods'] = PaymentMethods::getManualOrderPaymentMethods(
                $region,
                $this->paymentSettings($settings),
            );
        }

        return [
            'items' => $pricing['items'],
            'subtotalInPHP' => Decimal::toFloat($pricing['subtotalInPHP']),
            'discountAmountInPHP' => Decimal::toFloat($pricing['discountAmountInPHP']),
            'taxAmountInPHP' => Decimal::toFloat($pricing['taxAmountInPHP']),
            'shippingFeeInPHP' => Decimal::toFloat($pricing['shippingFeeInPHP']),
            'installationFeeInPHP' => Decimal::toFloat($pricing['installationFeeInPHP']),
            'installationRequested' => $pricing['installationRequested'],
            'installationEligible' => $pricing['installationEligible'],
            'installationPreviewFeeInPHP' => Decimal::toFloat($pricing['installationPreviewFeeInPHP']),
            'shippingLabel' => $pricing['shippingLabel'],
            'shippingRateId' => $pricing['shippingRateId'],
            'availableShippingMethods' => $pricing['availableShippingMethods'],
            'shippingRegion' => $pricing['shippingRegion'],
            'shippingZone' => $pricing['shippingZone'],
            'isDomestic' => $pricing['isDomestic'],
            'deliveryNote' => $pricing['deliveryNote'],
            'availablePaymentMethods' => $pricing['availablePaymentMethods'],
            'promotionCode' => $pricing['promotionCode'],
            'totalInPHP' => Decimal::toFloat($pricing['totalInPHP']),
            'currency' => $pricing['currency']->value,
            'exchangeRate' => (float) $pricing['exchangeRate'],
            'totalDisplay' => $pricing['totalDisplay'],
        ];
    }

    public function trackOrder(string $orderNumber, string $email): array
    {
        $order = Order::query()
            ->where('orderNumber', $orderNumber)
            ->with([
                'orderItems.product:id,name,slug,images',
                'user:id,email',
                'shipmentEvents' => fn ($q) => $q->orderBy('occurredAt'),
            ])
            ->first();

        if (! $order) {
            throw new NotFoundHttpException('Order not found.');
        }

        $normalized = strtolower(trim($email));
        $guestMatch = strtolower((string) $order->guestEmail) === $normalized;
        $userMatch = strtolower((string) ($order->user?->email ?? '')) === $normalized;
        if (! $guestMatch && ! $userMatch) {
            throw new NotFoundHttpException('Order not found.');
        }

        return $this->serializePublicOrder($order);
    }

    public function findMyOrders(string $userId): array
    {
        return Order::query()
            ->where('userId', $userId)
            ->with(['orderItems.product:id,name,slug,images'])
            ->orderByDesc('createdAt')
            ->get()
            ->map(fn (Order $o) => $this->serializePublicOrder($o))
            ->all();
    }

    public function findMyOrderByNumber(string $userId, string $orderNumber): array
    {
        $order = Order::query()
            ->where('orderNumber', $orderNumber)
            ->with([
                'orderItems.product:id,name,slug,images',
                'shipmentEvents' => fn ($q) => $q->orderBy('occurredAt'),
                'orderRequests' => fn ($q) => $q->orderByDesc('createdAt'),
            ])
            ->first();

        if (! $order || $order->userId !== $userId) {
            throw new NotFoundHttpException('Order not found.');
        }

        return $this->serializePublicOrder($order);
    }

    public function requestCancel(string $userId, string $orderNumber, string $reason): OrderRequest
    {
        return $this->createOrderRequest($userId, $orderNumber, OrderRequestType::Cancel, $reason, function (Order $order) {
            if (! in_array($order->shippingStatus, [ShippingStatus::Pending, ShippingStatus::Processing], true)) {
                throw new BadRequestException('This order can no longer be cancelled online.');
            }
            if ($order->paymentStatus === PaymentStatus::Paid && ! in_array($order->paymentMethod, [PaymentMethod::Cod, PaymentMethod::BankTransfer], true)) {
                throw new BadRequestException('Paid orders require staff assistance to cancel.');
            }
        });
    }

    public function requestReturn(string $userId, string $orderNumber, string $reason): OrderRequest
    {
        return $this->createOrderRequest($userId, $orderNumber, OrderRequestType::Return, $reason, function (Order $order) {
            if ($order->shippingStatus !== ShippingStatus::Delivered) {
                throw new BadRequestException('Returns are available after delivery is confirmed.');
            }
        });
    }

    /**
     * @param  callable(Order): void  $validate
     */
    private function createOrderRequest(
        string $userId,
        string $orderNumber,
        OrderRequestType $type,
        string $reason,
        callable $validate,
    ): OrderRequest {
        $order = Order::query()
            ->where('orderNumber', $orderNumber)
            ->with(['orderRequests' => fn ($q) => $q->where('type', $type)->where('status', OrderRequestStatus::Pending)])
            ->first();

        if (! $order || $order->userId !== $userId) {
            throw new NotFoundHttpException('Order not found.');
        }

        $validate($order);

        if ($order->orderRequests->isNotEmpty()) {
            throw new BadRequestException("A {$type->value} request is already pending for this order.");
        }

        $request = OrderRequest::query()->create([
            'orderId' => $order->id,
            'userId' => $userId,
            'type' => $type,
            'status' => OrderRequestStatus::Pending,
            'reason' => trim($reason),
        ]);

        OrderNote::query()->create([
            'orderId' => $order->id,
            'authorEmail' => 'customer',
            'body' => 'Customer '.strtolower($type->value)." request: ".trim($reason),
        ]);

        return $request;
    }

    public function resolveOrderRequest(
        string $orderId,
        string $requestId,
        string $action,
        string $staffEmail,
        ?string $staffNote = null,
    ): OrderRequest {
        $request = OrderRequest::query()
            ->where('id', $requestId)
            ->where('orderId', $orderId)
            ->with('order')
            ->first();

        if (! $request) {
            throw new NotFoundHttpException('Order request not found.');
        }
        if ($request->status !== OrderRequestStatus::Pending) {
            throw new BadRequestException('This request has already been processed.');
        }

        $note = $staffNote !== null && trim($staffNote) !== '' ? trim($staffNote) : null;

        if ($action === 'reject') {
            $request->update(['status' => OrderRequestStatus::Rejected, 'staffNote' => $note]);
            OrderNote::query()->create([
                'orderId' => $orderId,
                'authorEmail' => $staffEmail,
                'body' => 'Staff rejected '.strtolower($request->type->value).' request'.($note ? ": {$note}" : '.'),
            ]);
            $this->auditService->log([
                'userEmail' => $staffEmail,
                'action' => 'ORDER_REQUEST_REJECTED',
                'entity' => 'OrderRequest',
                'entityId' => $requestId,
            ]);

            return $request->fresh();
        }

        DB::transaction(function () use ($request, $orderId, $staffEmail, $note, $requestId) {
            $request->update(['status' => OrderRequestStatus::Approved, 'staffNote' => $note]);

            $orderUpdate = [];
            if ($request->type === OrderRequestType::Cancel) {
                $orderUpdate['shippingStatus'] = ShippingStatus::Cancelled;
                if ($request->order->paymentStatus === PaymentStatus::Paid) {
                    $orderUpdate['refundStatus'] = RefundStatus::Requested;
                }
            } elseif ($request->type === OrderRequestType::Return) {
                $orderUpdate['refundStatus'] = RefundStatus::Requested;
            }

            if ($orderUpdate !== []) {
                Order::query()->where('id', $orderId)->update($orderUpdate);
            }

            OrderNote::query()->create([
                'orderId' => $orderId,
                'authorEmail' => $staffEmail,
                'body' => 'Staff approved '.strtolower($request->type->value).' request'.($note ? ": {$note}" : '.'),
            ]);

            $this->auditService->log([
                'userEmail' => $staffEmail,
                'action' => 'ORDER_REQUEST_APPROVED',
                'entity' => 'OrderRequest',
                'entityId' => $requestId,
            ]);
        });

        return $request->fresh();
    }

    /**
     * @param  array<string, mixed>  $dto
     * @return array<string, mixed>
     */
    public function checkout(array $dto, ?AuthUser $authUser = null): array
    {
        $settings = $this->platformSettings->getRaw();
        if (! $settings->guestCheckoutEnabled && ! $authUser) {
            throw new BadRequestException('Please sign in to complete checkout.');
        }
        if (! $settings->guestCheckoutEnabled && empty($dto['userId']) && $authUser) {
            $dto['userId'] = $authUser->id;
        }

        $deliveryMethod = isset($dto['deliveryMethod'])
            ? DeliveryMethod::from($dto['deliveryMethod'])
            : DeliveryMethod::Delivery;

        if ($deliveryMethod !== DeliveryMethod::Pickup) {
            ShippingRegion::validateShippingAddress($dto['shippingAddress'], true);
        }

        $country = trim($dto['shippingAddress']['country']);
        $region = ShippingRegion::getShippingRegion($country);
        $domestic = ShippingRegion::isPhilippines($country);
        $allowedMethods = array_flip(PaymentMethods::getPaymentMethodsForRegion($region, $this->paymentSettings($settings)));

        if (! isset($allowedMethods[$dto['paymentMethod']])) {
            throw new BadRequestException(
                $domestic
                    ? 'Payment method not available for Philippines.'
                    : 'International checkout requires Stripe Card.',
            );
        }

        $paymentMethod = PaymentMethod::from($dto['paymentMethod']);
        $isSupportAssisted = $paymentMethod === PaymentMethod::SupportAssisted;

        if ($isSupportAssisted) {
            if (! $settings->supportAssistedCheckoutEnabled) {
                throw new BadRequestException('Support-assisted checkout is not available.');
            }
            if (! $domestic) {
                throw new BadRequestException('Support-assisted checkout is only available in the Philippines.');
            }
            if (! $authUser || $authUser->roleKey !== Permissions::CUSTOMER_ROLE_KEY) {
                throw new BadRequestException('Sign in to use support-assisted checkout.');
            }
            if (! empty($dto['userId']) && $dto['userId'] !== $authUser->id) {
                throw new BadRequestException('Invalid customer account for checkout.');
            }
            $dto['userId'] = $authUser->id;
        }

        $installationRequested = $dto['installationRequested'] ?? false;
        $pricing = $this->calculatePricing(
            $dto['items'],
            $dto['shippingAddress'],
            $dto['promotionCode'] ?? null,
            $deliveryMethod,
            $dto['userId'] ?? null,
            $dto['bundleId'] ?? null,
            $installationRequested,
            $dto['shippingRateId'] ?? null,
        );
        $pricedLineItems = $this->resolveLineItems($dto['items'], $dto['bundleId'] ?? null);
        $phpPerUsd = $this->platformSettings->getPhpPerUsd();
        $exchangeRate = $domestic ? '1' : $phpPerUsd;
        $currency = $domestic ? Currency::PHP : Currency::USD;

        $order = DB::transaction(function () use (
            $pricedLineItems,
            $pricing,
            $dto,
            $currency,
            $exchangeRate,
            $paymentMethod,
            $isSupportAssisted,
            $deliveryMethod,
            $settings,
        ) {
            $this->decrementStock($pricedLineItems);

            if ($pricing['promotionCode']) {
                $this->promotionsService->incrementUsage($pricing['promotionCode']);
            }

            $order = Order::query()->create([
                'orderNumber' => 'ORD-'.time().'-'.random_int(0, 99999),
                'userId' => $dto['userId'] ?? null,
                'guestEmail' => strtolower(trim($dto['guestEmail'])),
                'guestPhone' => isset($dto['guestPhone']) ? trim($dto['guestPhone']) : null,
                'currency' => $currency,
                'exchangeRate' => $exchangeRate,
                'subtotalInPHP' => $pricing['subtotalInPHP'],
                'taxAmountInPHP' => $pricing['taxAmountInPHP'],
                'discountAmountInPHP' => $pricing['discountAmountInPHP'],
                'shippingFeeInPHP' => $pricing['shippingFeeInPHP'],
                'shippingZone' => $pricing['shippingZone'],
                'shippingRateId' => $pricing['shippingRateId'],
                'installationFeeInPHP' => $pricing['installationFeeInPHP'],
                'installationRequested' => $pricing['installationRequested'],
                'totalAmountInPHP' => $pricing['totalInPHP'],
                'promotionCode' => $pricing['promotionCode'],
                'paymentMethod' => $paymentMethod,
                'paymentStatus' => PaymentStatus::Unpaid,
                'shippingStatus' => ShippingStatus::Pending,
                'quoteStatus' => $isSupportAssisted ? QuoteStatus::PendingReview : QuoteStatus::None,
                'deliveryMethod' => $deliveryMethod,
                'pickupLocationId' => $dto['pickupLocationId'] ?? null,
                'shippingAddress' => $dto['shippingAddress'],
                'customerNote' => $settings->checkoutOrderNotesEnabled
                    ? (isset($dto['customerNote']) ? trim($dto['customerNote']) : null)
                    : null,
            ]);

            foreach ($pricedLineItems as $line) {
                OrderItem::query()->create([
                    'orderId' => $order->id,
                    'productId' => $line['productId'],
                    'variantId' => $line['variantId'],
                    'variantLabel' => $line['variantLabel'],
                    'quantity' => $line['quantity'],
                    'unitPriceInPHP' => $line['unitPriceInPHP'],
                    'totalPriceInPHP' => Decimal::mul($line['unitPriceInPHP'], $line['quantity']),
                ]);
            }

            ShipmentEvent::query()->create([
                'orderId' => $order->id,
                'status' => 'ORDER_PLACED',
                'message' => 'Your order was received and is being processed.',
            ]);

            return $order->load(['orderItems.product:id,name']);
        });

        $this->abandonedCartsService->markRecovered($dto['guestEmail']);

        if ($isSupportAssisted && $settings->customerChatEnabled) {
            $this->supportChat->createWelcomeMessage($order->id);
        }

        $paymentSession = $this->paymentsService->createPaymentSession([
            'orderNumber' => $order->orderNumber,
            'orderId' => $order->id,
            'paymentMethod' => $paymentMethod,
            'totalAmountInPHP' => $order->totalAmountInPHP,
            'exchangeRate' => $order->exchangeRate,
            'currency' => $order->currency->value,
            'customerEmail' => $dto['guestEmail'],
        ]);

        if ($paymentSession['paymentSessionId'] || $paymentSession['paymentSessionUrl']) {
            $order->update([
                'paymentSessionId' => $paymentSession['paymentSessionId'],
                'paymentSessionUrl' => $paymentSession['paymentSessionUrl'],
            ]);
        }

        $invoicePdf = $this->buildInvoicePdfFromOrder($order);
        $this->emailService->sendOrderConfirmationEmail([
            'to' => $dto['guestEmail'],
            'orderNumber' => $order->orderNumber,
            'paymentMethod' => $order->paymentMethod->value,
            'totalAmount' => $order->currency === Currency::USD
                ? (float) Money::toUsdFromPhp($order->totalAmountInPHP, $order->exchangeRate)
                : (float) $order->totalAmountInPHP,
            'currency' => $order->currency->value,
            'invoicePdf' => $invoicePdf,
        ]);

        $result = $this->serializePublicOrder($order);
        $result['paymentSessionUrl'] = $paymentSession['paymentSessionUrl'];
        $result['paymentConfigured'] = $paymentSession['configured'];
        $result['paymentMessage'] = $paymentSession['message'] ?? null;
        $result['totalAmountInUSD'] = $order->currency === Currency::USD
            ? Money::toUsdFromPhp($order->totalAmountInPHP, $order->exchangeRate)
            : null;

        return $result;
    }

    /**
     * @param  array<string, mixed>  $dto
     * @return array<string, mixed>
     */
    public function createManualOrder(array $dto, string $staffEmail): array
    {
        $deliveryMethod = isset($dto['deliveryMethod'])
            ? DeliveryMethod::from($dto['deliveryMethod'])
            : DeliveryMethod::Delivery;

        if ($deliveryMethod !== DeliveryMethod::Pickup) {
            ShippingRegion::validateShippingAddress($dto['shippingAddress'], true);
        }

        $country = trim($dto['shippingAddress']['country']);
        $region = ShippingRegion::getShippingRegion($country);
        $domestic = ShippingRegion::isPhilippines($country);
        $settings = $this->platformSettings->getRaw();
        $allowedMethods = array_flip(PaymentMethods::getManualOrderPaymentMethods($region, $this->paymentSettings($settings)));

        if (! isset($allowedMethods[$dto['paymentMethod']])) {
            throw new BadRequestException(
                $domestic
                    ? 'Payment method not available for Philippines.'
                    : 'International checkout requires Stripe Card.',
            );
        }

        $pricing = $this->calculatePricing(
            $dto['items'],
            $dto['shippingAddress'],
            $dto['promotionCode'] ?? null,
            $deliveryMethod,
            $dto['userId'] ?? null,
        );
        $shippingFeeInPHP = isset($dto['shippingFeeInPHP'])
            ? Decimal::of($dto['shippingFeeInPHP'])
            : $pricing['shippingFeeInPHP'];
        $totalInPHP = isset($dto['shippingFeeInPHP'])
            ? $this->recalculateOrderTotal([
                'subtotalInPHP' => $pricing['subtotalInPHP'],
                'discountAmountInPHP' => $pricing['discountAmountInPHP'],
                'taxAmountInPHP' => $pricing['taxAmountInPHP'],
                'shippingFeeInPHP' => $shippingFeeInPHP,
                'installationFeeInPHP' => $pricing['installationFeeInPHP'],
            ])
            : $pricing['totalInPHP'];

        $pricedLineItems = $this->resolveLineItems($dto['items']);
        $exchangeRate = $domestic ? '1' : $this->platformSettings->getPhpPerUsd();
        $currency = $domestic ? Currency::PHP : Currency::USD;
        $paymentMethod = PaymentMethod::from($dto['paymentMethod']);

        $paymentStatus = (! empty($dto['markAsPaid']) || ($dto['paymentStatus'] ?? null) === PaymentStatus::Paid->value)
            ? PaymentStatus::Paid
            : (isset($dto['paymentStatus']) ? PaymentStatus::from($dto['paymentStatus']) : PaymentStatus::Unpaid);
        $shippingStatus = isset($dto['shippingStatus'])
            ? ShippingStatus::from($dto['shippingStatus'])
            : ShippingStatus::Pending;

        $order = DB::transaction(function () use (
            $pricedLineItems,
            $pricing,
            $dto,
            $currency,
            $exchangeRate,
            $paymentMethod,
            $deliveryMethod,
            $shippingFeeInPHP,
            $totalInPHP,
            $paymentStatus,
            $shippingStatus,
            $staffEmail,
        ) {
            $this->decrementStock($pricedLineItems);

            if ($pricing['promotionCode']) {
                $this->promotionsService->incrementUsage($pricing['promotionCode']);
            }

            $order = Order::query()->create([
                'orderNumber' => 'ORD-'.time().'-'.random_int(0, 99999),
                'userId' => $dto['userId'] ?? null,
                'guestEmail' => strtolower(trim($dto['guestEmail'])),
                'guestPhone' => isset($dto['guestPhone']) ? trim($dto['guestPhone']) : null,
                'currency' => $currency,
                'exchangeRate' => $exchangeRate,
                'subtotalInPHP' => $pricing['subtotalInPHP'],
                'taxAmountInPHP' => $pricing['taxAmountInPHP'],
                'discountAmountInPHP' => $pricing['discountAmountInPHP'],
                'shippingFeeInPHP' => $shippingFeeInPHP,
                'shippingZone' => $pricing['shippingZone'],
                'totalAmountInPHP' => $totalInPHP,
                'promotionCode' => $pricing['promotionCode'],
                'paymentMethod' => $paymentMethod,
                'paymentStatus' => $paymentStatus,
                'shippingStatus' => $shippingStatus,
                'deliveryMethod' => $deliveryMethod,
                'pickupLocationId' => $dto['pickupLocationId'] ?? null,
                'shippingAddress' => $dto['shippingAddress'],
            ]);

            foreach ($pricedLineItems as $line) {
                OrderItem::query()->create([
                    'orderId' => $order->id,
                    'productId' => $line['productId'],
                    'variantId' => $line['variantId'],
                    'variantLabel' => $line['variantLabel'],
                    'quantity' => $line['quantity'],
                    'unitPriceInPHP' => $line['unitPriceInPHP'],
                    'totalPriceInPHP' => Decimal::mul($line['unitPriceInPHP'], $line['quantity']),
                ]);
            }

            ShipmentEvent::query()->create([
                'orderId' => $order->id,
                'status' => 'ORDER_PLACED',
                'message' => "Manual order created by {$staffEmail}.",
            ]);

            if ($paymentStatus !== PaymentStatus::Unpaid) {
                OrderStatusHistory::query()->create([
                    'orderId' => $order->id,
                    'field' => 'paymentStatus',
                    'fromValue' => PaymentStatus::Unpaid->value,
                    'toValue' => $paymentStatus->value,
                    'changedByEmail' => $staffEmail,
                ]);
            }
            if ($shippingStatus !== ShippingStatus::Pending) {
                OrderStatusHistory::query()->create([
                    'orderId' => $order->id,
                    'field' => 'shippingStatus',
                    'fromValue' => ShippingStatus::Pending->value,
                    'toValue' => $shippingStatus->value,
                    'changedByEmail' => $staffEmail,
                ]);
            }

            OrderNote::query()->create([
                'orderId' => $order->id,
                'authorEmail' => $staffEmail,
                'body' => ! empty($dto['internalNote'])
                    ? 'Manual order — '.trim($dto['internalNote'])
                    : 'Manual order created from admin.',
            ]);

            return $order->load([
                'orderItems.product:id,name',
                'user:id,email,roleId',
                'statusHistory' => fn ($q) => $q->orderByDesc('createdAt')->limit(20),
                'notes' => fn ($q) => $q->orderByDesc('createdAt'),
            ]);
        });

        $skipPaymentSession = $paymentStatus === PaymentStatus::Paid
            || in_array($paymentMethod, [PaymentMethod::Cod, PaymentMethod::BankTransfer, PaymentMethod::BnplInstallment, PaymentMethod::SupportAssisted], true);

        if (! $skipPaymentSession) {
            $paymentSession = $this->paymentsService->createPaymentSession([
                'orderNumber' => $order->orderNumber,
                'orderId' => $order->id,
                'paymentMethod' => $paymentMethod,
                'totalAmountInPHP' => $order->totalAmountInPHP,
                'exchangeRate' => $order->exchangeRate,
                'currency' => $order->currency->value,
                'customerEmail' => $dto['guestEmail'],
            ]);

            if ($paymentSession['paymentSessionId'] || $paymentSession['paymentSessionUrl']) {
                $order->update([
                    'paymentSessionId' => $paymentSession['paymentSessionId'],
                    'paymentSessionUrl' => $paymentSession['paymentSessionUrl'],
                ]);
            }
        }

        if (($dto['sendConfirmationEmail'] ?? true) !== false) {
            try {
                $invoicePdf = $this->buildInvoicePdfFromOrder($order);
                $this->emailService->sendOrderConfirmationEmail([
                    'to' => $dto['guestEmail'],
                    'orderNumber' => $order->orderNumber,
                    'paymentMethod' => $order->paymentMethod->value,
                    'totalAmount' => $order->currency === Currency::USD
                        ? (float) Money::toUsdFromPhp($order->totalAmountInPHP, $order->exchangeRate)
                        : (float) $order->totalAmountInPHP,
                    'currency' => $order->currency->value,
                    'invoicePdf' => $invoicePdf,
                ]);
            } catch (\Throwable $e) {
                \Illuminate\Support\Facades\Log::warning('Manual order confirmation email failed', [
                    'orderId' => $order->id,
                    'orderNumber' => $order->orderNumber,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        $this->auditService->log([
            'userEmail' => $staffEmail,
            'action' => 'ORDER_MANUAL_CREATE',
            'entity' => 'Order',
            'entityId' => $order->id,
            'details' => [
                'orderNumber' => $order->orderNumber,
                'paymentStatus' => $paymentStatus->value,
                'shippingStatus' => $shippingStatus->value,
                'itemCount' => $order->orderItems->count(),
            ],
        ]);

        return $this->serializeOrder($order);
    }

    /**
     * @param  array<string, mixed>  $query
     * @return array{data: list<array<string, mixed>>, meta: array<string, mixed>}
     */
    public function findAll(array $query): array
    {
        $builder = Order::query()->with(['orderItems.product:id,name', 'user:id,email']);

        if (! empty($query['paymentStatus'])) {
            $builder->where('paymentStatus', $query['paymentStatus']);
        }
        if (! empty($query['shippingStatus'])) {
            $builder->where('shippingStatus', $query['shippingStatus']);
        }
        if (($query['fulfillment'] ?? null) === 'true') {
            $builder->where('paymentStatus', PaymentStatus::Paid)
                ->whereIn('shippingStatus', [ShippingStatus::Pending, ShippingStatus::Processing]);
        }
        if (($query['followUp'] ?? null) === 'true') {
            $this->applyFollowUpWhere($builder);
        }
        if (! empty($query['search'])) {
            $builder->where('orderNumber', 'ilike', '%'.trim($query['search']).'%');
        }
        if (! empty($query['from']) || ! empty($query['to'])) {
            if (! empty($query['from'])) {
                $builder->where('createdAt', '>=', $query['from']);
            }
            if (! empty($query['to'])) {
                $end = new \DateTimeImmutable($query['to']);
                $end = $end->setTime(23, 59, 59);
                $builder->where('createdAt', '<=', $end);
            }
        }

        $page = max(1, (int) ($query['page'] ?? 1));
        $limit = min((int) ($query['limit'] ?? 20), 100);
        $filtered = clone $builder;
        $total = $filtered->count();

        $orders = (clone $builder)->orderByDesc('createdAt')
            ->skip(($page - 1) * $limit)
            ->take($limit)
            ->get();

        $summaryBase = clone $builder;
        $paidAgg = (clone $summaryBase)->where('paymentStatus', PaymentStatus::Paid)->sum('totalAmountInPHP');
        $unpaidCount = (clone $summaryBase)->where('paymentStatus', PaymentStatus::Unpaid)->count();
        $pendingFulfillment = (clone $summaryBase)
            ->where('paymentStatus', PaymentStatus::Paid)
            ->whereIn('shippingStatus', [ShippingStatus::Pending, ShippingStatus::Processing])
            ->count();

        return [
            'data' => $orders->map(fn (Order $o) => $this->serializeOrder($o))->all(),
            'meta' => [
                'total' => $total,
                'page' => $page,
                'limit' => $limit,
                'totalPages' => (int) ceil($total / $limit),
                'summary' => [
                    'revenueInPHP' => (float) $paidAgg,
                    'unpaidCount' => $unpaidCount,
                    'pendingFulfillment' => $pendingFulfillment,
                ],
            ],
        ];
    }

    public function findOne(string $id): array
    {
        $order = Order::query()
            ->with([
                'orderItems.product',
                'user:id,email,roleId',
                'supportConversation:id,orderId',
                'statusHistory' => fn ($q) => $q->orderByDesc('createdAt')->limit(20),
                'notes' => fn ($q) => $q->orderByDesc('createdAt'),
                'orderRequests' => fn ($q) => $q->orderByDesc('createdAt'),
            ])
            ->find($id);

        if (! $order) {
            throw new NotFoundHttpException("Order not found: {$id}");
        }

        return $this->serializeOrder($order);
    }

    public function addNote(string $id, string $body, string $authorEmail): OrderNote
    {
        $this->findOne($id);
        $note = OrderNote::query()->create([
            'orderId' => $id,
            'authorEmail' => $authorEmail,
            'body' => $body,
        ]);
        $this->auditService->log([
            'userEmail' => $authorEmail,
            'action' => 'ORDER_NOTE_ADDED',
            'entity' => 'Order',
            'entityId' => $id,
        ]);

        return $note;
    }

    /**
     * @param  array<string, mixed>  $dto
     */
    public function processRefund(string $id, array $dto, string $changedByEmail): array
    {
        $existing = Order::query()->find($id);
        if (! $existing) {
            throw new NotFoundHttpException("Order not found: {$id}");
        }

        $refundStatus = RefundStatus::from($dto['refundStatus']);

        $order = DB::transaction(function () use ($id, $existing, $dto, $changedByEmail, $refundStatus) {
            OrderStatusHistory::query()->create([
                'orderId' => $id,
                'field' => 'refundStatus',
                'fromValue' => $existing->refundStatus->value,
                'toValue' => $refundStatus->value,
                'changedByEmail' => $changedByEmail,
            ]);

            $existing->update([
                'refundStatus' => $refundStatus,
                'refundAmountInPHP' => $dto['refundAmountInPHP'],
                'refundReason' => $dto['refundReason'] ?? null,
                'paymentStatus' => $refundStatus === RefundStatus::Processed
                    ? PaymentStatus::Refunded
                    : $existing->paymentStatus,
            ]);

            return Order::query()
                ->with([
                    'orderItems.product:id,name',
                    'user:id,email',
                    'statusHistory' => fn ($q) => $q->orderByDesc('createdAt')->limit(20),
                    'notes' => fn ($q) => $q->orderByDesc('createdAt'),
                ])
                ->findOrFail($id);
        });

        $this->auditService->log([
            'userEmail' => $changedByEmail,
            'action' => 'ORDER_REFUND',
            'entity' => 'Order',
            'entityId' => $id,
            'details' => ['refundStatus' => $refundStatus->value, 'amount' => $dto['refundAmountInPHP']],
        ]);

        return $this->serializeOrder($order);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(string $id, array $data, ?string $changedByEmail = null): array
    {
        $existing = Order::query()->with('user:id,email')->find($id);
        if (! $existing) {
            throw new NotFoundHttpException("Order not found: {$id}");
        }

        if (! empty($data['shippingStatus']) && $data['shippingStatus'] !== $existing->shippingStatus->value) {
            try {
                ShippingWorkflow::assertShippingTransition(
                    $existing->shippingStatus->value,
                    $data['shippingStatus'],
                );
            } catch (\Throwable $e) {
                throw new BadRequestException($e->getMessage());
            }
        }

        $order = DB::transaction(function () use ($id, $existing, $data, $changedByEmail) {
            if (! empty($data['paymentStatus']) && $data['paymentStatus'] !== $existing->paymentStatus->value) {
                OrderStatusHistory::query()->create([
                    'orderId' => $id,
                    'field' => 'paymentStatus',
                    'fromValue' => $existing->paymentStatus->value,
                    'toValue' => $data['paymentStatus'],
                    'changedByEmail' => $changedByEmail,
                ]);
            }
            if (! empty($data['shippingStatus']) && $data['shippingStatus'] !== $existing->shippingStatus->value) {
                OrderStatusHistory::query()->create([
                    'orderId' => $id,
                    'field' => 'shippingStatus',
                    'fromValue' => $existing->shippingStatus->value,
                    'toValue' => $data['shippingStatus'],
                    'changedByEmail' => $changedByEmail,
                ]);
            }

            $shippingFeeInPHP = isset($data['shippingFeeInPHP'])
                ? Decimal::of($data['shippingFeeInPHP'])
                : Decimal::of($existing->shippingFeeInPHP);
            $installationFeeInPHP = isset($data['installationFeeInPHP'])
                ? Decimal::of($data['installationFeeInPHP'])
                : Decimal::of($existing->installationFeeInPHP ?? 0);
            $feesChanged = isset($data['shippingFeeInPHP']) || isset($data['installationFeeInPHP']);

            $updates = array_filter([
                'paymentStatus' => ! empty($data['paymentStatus']) ? PaymentStatus::from($data['paymentStatus']) : null,
                'shippingStatus' => ! empty($data['shippingStatus']) ? ShippingStatus::from($data['shippingStatus']) : null,
                'trackingNumber' => $data['trackingNumber'] ?? null,
                'carrier' => $data['carrier'] ?? null,
                'estimatedDeliveryAt' => ! empty($data['estimatedDeliveryAt']) ? $data['estimatedDeliveryAt'] : null,
            ], fn ($v) => $v !== null);

            if ($feesChanged) {
                $updates['shippingFeeInPHP'] = $shippingFeeInPHP;
                $updates['installationFeeInPHP'] = $installationFeeInPHP;
                $updates['totalAmountInPHP'] = $this->recalculateOrderTotal([
                    'subtotalInPHP' => $existing->subtotalInPHP,
                    'discountAmountInPHP' => $existing->discountAmountInPHP,
                    'taxAmountInPHP' => $existing->taxAmountInPHP,
                    'shippingFeeInPHP' => $shippingFeeInPHP,
                    'installationFeeInPHP' => $installationFeeInPHP,
                ]);
            }

            $existing->update($updates);

            if (($data['shippingStatus'] ?? null) === ShippingStatus::Shipped->value && ! empty($data['trackingNumber'])) {
                ShipmentEvent::query()->create([
                    'orderId' => $id,
                    'status' => 'SHIPPED',
                    'message' => 'Shipped via '.($data['carrier'] ?? 'courier').". Tracking: {$data['trackingNumber']}",
                ]);
            }

            return Order::query()
                ->with([
                    'orderItems.product:id,name',
                    'user:id,email',
                    'statusHistory' => fn ($q) => $q->orderByDesc('createdAt')->limit(20),
                    'notes' => fn ($q) => $q->orderByDesc('createdAt'),
                ])
                ->findOrFail($id);
        });

        $notifyEmail = $order->user?->email ?? $order->guestEmail;
        if ($notifyEmail && ($data['shippingStatus'] ?? null) === ShippingStatus::Shipped->value && ! empty($data['trackingNumber'])) {
            $this->emailService->sendOrderShippedEmail([
                'to' => $notifyEmail,
                'orderNumber' => $order->orderNumber,
                'carrier' => $data['carrier'] ?? 'Courier',
                'trackingNumber' => $data['trackingNumber'],
            ]);
        } elseif ($notifyEmail && (! empty($data['paymentStatus']) || ! empty($data['shippingStatus']))) {
            $this->emailService->sendOrderStatusEmail([
                'to' => $notifyEmail,
                'orderNumber' => $order->orderNumber,
                'shippingStatus' => $order->shippingStatus->value,
                'paymentStatus' => $order->paymentStatus->value,
            ]);
        }

        if ($changedByEmail) {
            $this->auditService->log([
                'userEmail' => $changedByEmail,
                'action' => 'ORDER_UPDATED',
                'entity' => 'Order',
                'entityId' => $id,
                'details' => $data,
            ]);
        }

        return $this->serializeOrder($order);
    }

    /**
     * @param  array<string, mixed>  $dto
     */
    public function updateQuote(string $id, array $dto, string $staffEmail): array
    {
        $existing = Order::query()->with('orderItems')->find($id);
        if (! $existing) {
            throw new NotFoundHttpException("Order not found: {$id}");
        }

        $quoteEligible = $existing->paymentMethod === PaymentMethod::SupportAssisted
            || $existing->quoteStatus !== QuoteStatus::None;
        if (! $quoteEligible) {
            throw new BadRequestException('This order is not eligible for quote editing.');
        }

        $subtotalInPHP = Decimal::of($existing->subtotalInPHP);
        $pricedLineItems = null;

        if (! empty($dto['items'])) {
            $pricedLineItems = $this->resolveLineItems($dto['items']);
            $subtotalInPHP = '0.00';
            foreach ($pricedLineItems as $line) {
                $subtotalInPHP = Decimal::add($subtotalInPHP, Decimal::mul($line['unitPriceInPHP'], $line['quantity']));
            }

            DB::transaction(function () use ($existing, $pricedLineItems) {
                foreach ($existing->orderItems as $old) {
                    $this->incrementStock($old->productId, $old->variantId, $old->quantity);
                }
                $this->decrementStock($pricedLineItems);
            });
        }

        $discountAmountInPHP = isset($dto['discountAmountInPHP'])
            ? Decimal::of($dto['discountAmountInPHP'])
            : Decimal::of($existing->discountAmountInPHP);
        $taxAmountInPHP = isset($dto['taxAmountInPHP'])
            ? Decimal::of($dto['taxAmountInPHP'])
            : Decimal::of($existing->taxAmountInPHP);
        $shippingFeeInPHP = isset($dto['shippingFeeInPHP'])
            ? Decimal::of($dto['shippingFeeInPHP'])
            : Decimal::of($existing->shippingFeeInPHP);
        $installationFeeInPHP = isset($dto['installationFeeInPHP'])
            ? Decimal::of($dto['installationFeeInPHP'])
            : Decimal::of($existing->installationFeeInPHP ?? 0);
        $installationRequested = $dto['installationRequested'] ?? $existing->installationRequested;

        $totalAmountInPHP = $this->recalculateOrderTotal([
            'subtotalInPHP' => $subtotalInPHP,
            'discountAmountInPHP' => $discountAmountInPHP,
            'taxAmountInPHP' => $taxAmountInPHP,
            'shippingFeeInPHP' => $shippingFeeInPHP,
            'installationFeeInPHP' => $installationFeeInPHP,
        ]);

        $nextQuoteStatus = isset($dto['quoteStatus'])
            ? QuoteStatus::from($dto['quoteStatus'])
            : (! empty($dto['notifyCustomer']) ? QuoteStatus::QuoteSent : $existing->quoteStatus);

        $order = DB::transaction(function () use (
            $id,
            $existing,
            $pricedLineItems,
            $subtotalInPHP,
            $discountAmountInPHP,
            $taxAmountInPHP,
            $shippingFeeInPHP,
            $installationFeeInPHP,
            $installationRequested,
            $totalAmountInPHP,
            $nextQuoteStatus,
            $dto,
        ) {
            if ($pricedLineItems !== null) {
                OrderItem::query()->where('orderId', $id)->delete();
                foreach ($pricedLineItems as $line) {
                    OrderItem::query()->create([
                        'orderId' => $id,
                        'productId' => $line['productId'],
                        'variantId' => $line['variantId'],
                        'variantLabel' => $line['variantLabel'],
                        'quantity' => $line['quantity'],
                        'unitPriceInPHP' => $line['unitPriceInPHP'],
                        'totalPriceInPHP' => Decimal::mul($line['unitPriceInPHP'], $line['quantity']),
                    ]);
                }
            }

            $updates = [
                'subtotalInPHP' => $subtotalInPHP,
                'discountAmountInPHP' => $discountAmountInPHP,
                'taxAmountInPHP' => $taxAmountInPHP,
                'shippingFeeInPHP' => $shippingFeeInPHP,
                'installationFeeInPHP' => $installationFeeInPHP,
                'installationRequested' => $installationRequested,
                'totalAmountInPHP' => $totalAmountInPHP,
                'quoteStatus' => $nextQuoteStatus,
            ];
            if (! empty($dto['paymentMethod'])) {
                $updates['paymentMethod'] = PaymentMethod::from($dto['paymentMethod']);
            }
            $existing->update($updates);

            return Order::query()
                ->with([
                    'orderItems.product:id,name',
                    'user:id,email',
                    'statusHistory' => fn ($q) => $q->orderByDesc('createdAt')->limit(20),
                    'notes' => fn ($q) => $q->orderByDesc('createdAt'),
                ])
                ->findOrFail($id);
        });

        if (! empty($dto['notifyCustomer'])) {
            $total = number_format(Decimal::toFloat($totalAmountInPHP), 2);
            $this->supportChat->addSystemMessage(
                $id,
                "Your quote was updated. New total: ₱{$total}. Reply here if you have questions.",
            );
        }

        $this->auditService->log([
            'userEmail' => $staffEmail,
            'action' => 'ORDER_QUOTE_UPDATED',
            'entity' => 'Order',
            'entityId' => $id,
            'details' => ['quoteStatus' => $nextQuoteStatus->value, 'totalAmountInPHP' => Decimal::toFloat($totalAmountInPHP)],
        ]);

        return $this->serializeOrder($order);
    }

    public function cancelQuote(string $id, string $staffEmail): array
    {
        $existing = Order::query()->with(['orderItems', 'user:id,email'])->find($id);
        if (! $existing) {
            throw new NotFoundHttpException("Order not found: {$id}");
        }

        $quoteEligible = $existing->paymentMethod === PaymentMethod::SupportAssisted
            || $existing->quoteStatus !== QuoteStatus::None;
        if (! $quoteEligible) {
            throw new BadRequestException('This order is not an active quote.');
        }
        if ($existing->quoteStatus === QuoteStatus::Cancelled) {
            throw new BadRequestException('Quote is already cancelled.');
        }

        $order = DB::transaction(function () use ($id, $existing, $staffEmail) {
            foreach ($existing->orderItems as $line) {
                $this->incrementStock($line->productId, $line->variantId, $line->quantity);
            }

            OrderStatusHistory::query()->create([
                'orderId' => $id,
                'field' => 'quoteStatus',
                'fromValue' => $existing->quoteStatus->value,
                'toValue' => QuoteStatus::Cancelled->value,
                'changedByEmail' => $staffEmail,
            ]);

            $existing->update([
                'quoteStatus' => QuoteStatus::Cancelled,
                'shippingStatus' => ShippingStatus::Cancelled,
                'quoteStaleAt' => null,
            ]);

            return Order::query()
                ->with([
                    'orderItems.product:id,name',
                    'user:id,email',
                    'statusHistory' => fn ($q) => $q->orderByDesc('createdAt')->limit(20),
                    'notes' => fn ($q) => $q->orderByDesc('createdAt'),
                ])
                ->findOrFail($id);
        });

        $this->supportChat->addSystemMessage(
            $id,
            'This quote was cancelled. Stock has been released. Contact us if you would like to place a new order.',
        );
        $this->supportChat->resolveConversation($id);

        $this->auditService->log([
            'userEmail' => $staffEmail,
            'action' => 'ORDER_QUOTE_CANCELLED',
            'entity' => 'Order',
            'entityId' => $id,
        ]);

        return $this->serializeOrder($order);
    }

    /**
     * @return array{sent: bool, to: string}
     */
    public function sendPaymentReminder(string $id, string $staffEmail): array
    {
        $order = Order::query()->with('user:id,email')->find($id);
        if (! $order) {
            throw new NotFoundHttpException("Order not found: {$id}");
        }
        if ($order->paymentStatus === PaymentStatus::Paid) {
            throw new BadRequestException('Order is already paid.');
        }

        $email = $order->user?->email ?? $order->guestEmail;
        if (! $email) {
            throw new BadRequestException('No customer email on file.');
        }

        $storefrontUrl = AppUrls::getStorefrontUrl();
        $accountUrl = "{$storefrontUrl}/account/orders/{$order->orderNumber}";

        $this->emailService->sendPaymentReminderEmail([
            'to' => $email,
            'orderNumber' => $order->orderNumber,
            'totalAmount' => (float) $order->totalAmountInPHP,
            'paymentMethod' => $order->paymentMethod->value,
            'accountUrl' => $accountUrl,
        ]);

        $total = number_format((float) $order->totalAmountInPHP, 2);
        $this->supportChat->addSystemMessage(
            $id,
            "We sent a payment reminder to your email. Total due: ₱{$total}.",
        );

        $this->auditService->log([
            'userEmail' => $staffEmail,
            'action' => 'ORDER_PAYMENT_REMINDER',
            'entity' => 'Order',
            'entityId' => $id,
        ]);

        return ['sent' => true, 'to' => $email];
    }

    /**
     * @return array<string, int>
     */
    public function getDashboardStats(): array
    {
        $this->refreshStaleQuoteFlags();

        $startOfDay = now()->startOfDay();
        $followUpQuery = Order::query();
        $this->applyFollowUpWhere($followUpQuery);

        return [
            'ordersToday' => Order::query()->where('createdAt', '>=', $startOfDay)->count(),
            'pendingFulfillment' => Order::query()
                ->whereIn('shippingStatus', [ShippingStatus::Pending, ShippingStatus::Processing])
                ->where('paymentStatus', PaymentStatus::Paid)
                ->count(),
            'paymentExceptions' => Order::query()->where(function (Builder $q) {
                $q->where('paymentStatus', PaymentStatus::Failed)
                    ->orWhere(function (Builder $q2) {
                        $q2->where('paymentStatus', PaymentStatus::Unpaid)
                            ->where('createdAt', '<', now()->subHours(48));
                    });
            })->count(),
            'totalOrders' => Order::query()->count(),
            'quotesAwaitingReview' => Order::query()->where('quoteStatus', QuoteStatus::PendingReview)->count(),
            'staleQuotes' => Order::query()
                ->whereNotNull('quoteStaleAt')
                ->whereIn('quoteStatus', [QuoteStatus::PendingReview, QuoteStatus::QuoteSent])
                ->count(),
            'followUpCount' => $followUpQuery->count(),
        ];
    }

    public function getInvoicePdfForCustomer(string $userId, string $orderNumber): string
    {
        $order = Order::query()
            ->where('orderNumber', $orderNumber)
            ->with(['orderItems.product:id,name'])
            ->first();

        if (! $order || $order->userId !== $userId) {
            throw new NotFoundHttpException('Order not found.');
        }

        return $this->buildInvoicePdfFromOrder($order);
    }

    /**
     * @return array{pdf: string, orderNumber: string}
     */
    public function getInvoicePdfForAdmin(string $orderId): array
    {
        $order = Order::query()
            ->with(['orderItems.product:id,name'])
            ->find($orderId);

        if (! $order) {
            throw new NotFoundHttpException('Order not found.');
        }

        return [
            'pdf' => $this->buildInvoicePdfFromOrder($order),
            'orderNumber' => $order->orderNumber,
        ];
    }

    /**
     * @param  list<array{productId: string, variantId?: string|null, quantity: int}>  $items
     * @param  array<string, mixed>|null  $shippingAddress
     * @return array<string, mixed>
     */
    private function calculatePricing(
        array $items,
        ?array $shippingAddress = null,
        ?string $promotionCode = null,
        ?DeliveryMethod $deliveryMethod = null,
        ?string $userId = null,
        ?string $bundleId = null,
        bool $installationRequested = false,
        ?string $shippingRateId = null,
    ): array {
        $priced = $this->validateAndPriceItems($items, $bundleId);
        $country = trim($shippingAddress['country'] ?? 'PH');
        $region = ShippingRegion::getShippingRegion($country);
        $domestic = $region === ShippingRegion::REGION_PH;
        $shippingZone = ShippingZone::getShippingZoneForAddress($shippingAddress);
        $shippingOptions = $this->listShippingOptions(
            $country,
            Decimal::toFloat($priced['subtotalInPHP']),
            $priced['totalWeightGrams'],
            $deliveryMethod,
            $shippingAddress,
        );
        $shipping = $this->pickShippingOption($shippingOptions, $shippingRateId);

        $promo = $this->promotionsService->validateAndCalculate(
            $promotionCode,
            array_map(fn ($item) => [
                'productId' => $item['productId'],
                'lineTotalInPHP' => $item['lineTotalInPHP'],
            ], $priced['items']),
            $userId,
        );

        $afterBundle = Decimal::sub($priced['subtotalInPHP'], $priced['bundleDiscountInPHP']);
        $afterDiscount = Decimal::sub($afterBundle, $promo['discountInPHP']);

        $settings = $this->platformSettings->getRaw();
        $vat = $this->resolveDomesticVat($afterDiscount, $settings, $domestic);
        $taxAmountInPHP = $vat['taxAmountInPHP'];

        $installationPricing = $this->resolveInstallationFee($priced['items'], $installationRequested);
        $shippingFee = Decimal::of($shipping['feeInPHP']);
        $installationFee = $installationPricing['feeInPHP'];
        $merchandiseTotal = $vat['inclusive']
            ? $afterDiscount
            : Decimal::add($afterDiscount, $taxAmountInPHP);
        $totalInPHP = Decimal::add($merchandiseTotal, Decimal::add($shippingFee, $installationFee));

        $exchangeRate = $domestic ? '1' : (string) $settings->phpPerUsd;
        $currency = $domestic ? Currency::PHP : Currency::USD;
        $paymentMethods = PaymentMethods::getPaymentMethodsForRegion($region, $this->paymentSettings($settings));
        $discountTotal = Decimal::add($priced['bundleDiscountInPHP'], $promo['discountInPHP']);

        return [
            'items' => $priced['items'],
            'subtotalInPHP' => $priced['subtotalInPHP'],
            'discountAmountInPHP' => $discountTotal,
            'taxAmountInPHP' => $taxAmountInPHP,
            'shippingFeeInPHP' => $shippingFee,
            'installationFeeInPHP' => $installationFee,
            'installationRequested' => $installationPricing['requested'],
            'installationEligible' => $installationPricing['eligible'],
            'installationPreviewFeeInPHP' => $installationPricing['previewFeeInPHP'],
            'totalInPHP' => $totalInPHP,
            'promotionCode' => $promo['promotionCode'],
            'shippingLabel' => $shipping['label'],
            'shippingRateId' => $shipping['rateId'],
            'availableShippingMethods' => $shippingOptions,
            'shippingRegion' => $region,
            'shippingZone' => $shippingZone,
            'isDomestic' => $domestic,
            'deliveryNote' => ShippingRegion::getDeliveryNote(
                $region,
                (float) $shipping['feeInPHP'],
                Decimal::toFloat($priced['subtotalInPHP']),
                (float) $settings->freeShippingMinPHP,
                $settings->freeShippingEnabled,
            ),
            'availablePaymentMethods' => $paymentMethods,
            'currency' => $currency,
            'exchangeRate' => $exchangeRate,
            'totalDisplay' => $currency === Currency::USD
                ? (float) Money::toUsdFromPhp($totalInPHP, $exchangeRate)
                : Decimal::toFloat($totalInPHP),
        ];
    }

    /**
     * @param  list<array{productId: string, variantId?: string|null, quantity: int}>  $items
     * @return list<array<string, mixed>>
     */
    private function resolveLineItems(array $items, ?string $bundleId = null): array
    {
        $resolved = $this->validateAndPriceItems($items, $bundleId);

        return array_map(fn ($item) => [
            'productId' => $item['productId'],
            'variantId' => $item['variantId'] ?? null,
            'variantLabel' => $item['variantLabel'] ?? null,
            'name' => $item['name'],
            'quantity' => $item['quantity'],
            'unitPriceInPHP' => Decimal::of($item['unitPriceInPHP']),
        ], $resolved['items']);
    }

    /**
     * @param  list<array{productId: string, variantId?: string|null, quantity: int, name?: string, unitPriceInPHP?: string}>  $lines
     */
    private function decrementStock(array $lines): void
    {
        foreach ($lines as $line) {
            $qty = $line['quantity'];
            if (! empty($line['variantId'])) {
                $updated = DB::table('ProductVariant')
                    ->where('id', $line['variantId'])
                    ->where('stockQuantity', '>=', $qty)
                    ->decrement('stockQuantity', $qty);
                if ($updated !== 1) {
                    throw new BadRequestException('Insufficient stock for '.($line['name'] ?? 'item'));
                }
            } else {
                $updated = DB::table('Product')
                    ->where('id', $line['productId'])
                    ->where('stockQuantity', '>=', $qty)
                    ->decrement('stockQuantity', $qty);
                if ($updated !== 1) {
                    throw new BadRequestException('Insufficient stock for '.($line['name'] ?? 'item'));
                }
            }
        }
    }

    private function incrementStock(string $productId, ?string $variantId, int $quantity): void
    {
        if ($variantId) {
            DB::table('ProductVariant')->where('id', $variantId)->increment('stockQuantity', $quantity);
        } else {
            DB::table('Product')->where('id', $productId)->increment('stockQuantity', $quantity);
        }
    }

    /**
     * @param  list<array{productId: string, variantId?: string|null, quantity: int}>  $items
     * @return array{items: list<array<string, mixed>>, subtotalInPHP: string, totalWeightGrams: int, bundleDiscountInPHP: string}
     */
    private function validateAndPriceItems(array $items, ?string $bundleId = null): array
    {
        $productIds = array_values(array_unique(array_column($items, 'productId')));
        $products = Product::query()
            ->whereIn('id', $productIds)
            ->with(['variants' => fn ($q) => $q->where('isActive', true)])
            ->get()
            ->keyBy('id');

        if ($products->count() !== count($productIds)) {
            throw new BadRequestException('One or more products are invalid.');
        }

        $subtotalInPHP = '0.00';
        $totalWeightGrams = 0;
        $pricedItems = [];

        foreach ($items as $item) {
            $product = $products[$item['productId']];
            $variant = ! empty($item['variantId'])
                ? $product->variants->firstWhere('id', $item['variantId'])
                : null;

            if (! empty($item['variantId']) && ! $variant) {
                throw new BadRequestException("Invalid variant for {$product->name}");
            }
            if ($product->variants->isNotEmpty() && empty($item['variantId'])) {
                throw new BadRequestException("Please select a variant for {$product->name}");
            }

            $stock = $variant ? $variant->stockQuantity : $product->stockQuantity;
            if ($stock < $item['quantity']) {
                throw new BadRequestException("Insufficient stock for {$product->name}");
            }

            $unitPrice = $variant ? $variant->priceInPHP : $product->priceInPHP;
            $weight = $variant?->weightInGrams ?? $product->weightInGrams;
            $totalWeightGrams += (int) $weight * $item['quantity'];
            $unitPriceInPHP = (float) $unitPrice;
            $subtotalInPHP = Decimal::add($subtotalInPHP, Decimal::mul($unitPrice, $item['quantity']));

            $installationUnitFee = $product->installationAvailable && $product->installationFeeInPHP !== null
                ? (float) $product->installationFeeInPHP
                : 0.0;

            $pricedItems[] = [
                'productId' => $product->id,
                'variantId' => $variant?->id,
                'variantLabel' => $variant?->name,
                'name' => $variant ? "{$product->name} — {$variant->name}" : $product->name,
                'slug' => $product->slug,
                'quantity' => $item['quantity'],
                'unitPriceInPHP' => $unitPriceInPHP,
                'lineTotalInPHP' => $unitPriceInPHP * $item['quantity'],
                'inStock' => $stock >= $item['quantity'],
                'installationAvailable' => $product->installationAvailable,
                'installationUnitFeeInPHP' => $installationUnitFee,
            ];
        }

        $bundleDiscountInPHP = '0.00';
        if ($bundleId) {
            $bundle = ProductBundle::query()
                ->where('id', $bundleId)
                ->where('isActive', true)
                ->with('items')
                ->first();

            if (! $bundle) {
                throw new BadRequestException('Bundle is invalid or no longer available.');
            }

            $cartMap = [];
            foreach ($items as $item) {
                $key = $item['productId'].':'.($item['variantId'] ?? '');
                $cartMap[$key] = ($cartMap[$key] ?? 0) + $item['quantity'];
            }

            foreach ($bundle->items as $bundleItem) {
                $key = $bundleItem->productId.':'.($bundleItem->variantId ?? '');
                $cartQty = $cartMap[$key] ?? 0;
                if ($cartQty < $bundleItem->quantity) {
                    throw new BadRequestException('Cart does not match the selected bundle.');
                }
                $cartMap[$key] = $cartQty - $bundleItem->quantity;
            }

            if (array_filter($cartMap, fn ($qty) => $qty > 0) !== []) {
                throw new BadRequestException('Cart does not match the selected bundle.');
            }

            $bundleDiscountInPHP = Decimal::div(
                Decimal::mul($subtotalInPHP, $bundle->discountPercent),
                100,
            );
        }

        return [
            'items' => $pricedItems,
            'subtotalInPHP' => $subtotalInPHP,
            'totalWeightGrams' => $totalWeightGrams,
            'bundleDiscountInPHP' => $bundleDiscountInPHP,
        ];
    }

    /**
     * @param  list<array{installationAvailable: bool, installationUnitFeeInPHP: float, quantity: int}>  $pricedItems
     * @return array{eligible: bool, requested: bool, previewFeeInPHP: string, feeInPHP: string}
     */
    private function resolveInstallationFee(array $pricedItems, bool $installationRequested): array
    {
        $eligible = false;
        $previewFee = 0.0;

        foreach ($pricedItems as $item) {
            if ($item['installationAvailable'] && $item['installationUnitFeeInPHP'] > 0) {
                $eligible = true;
                $previewFee += $item['installationUnitFeeInPHP'] * $item['quantity'];
            }
        }

        if ($installationRequested && ! $eligible) {
            throw new BadRequestException('Installation is not available for items in this cart.');
        }

        $feeAmount = ($installationRequested && $eligible) ? $previewFee : 0.0;

        return [
            'eligible' => $eligible,
            'requested' => $installationRequested && $eligible,
            'previewFeeInPHP' => Decimal::of($previewFee),
            'feeInPHP' => Decimal::of($feeAmount),
        ];
    }

    /**
     * @param  list<array{id: string|null, label: string, feeInPHP: float|int, estimatedDays?: string|null}>  $options
     * @return array{feeInPHP: float, label: string, rateId: string|null}
     */
    private function pickShippingOption(array $options, ?string $shippingRateId): array
    {
        if ($shippingRateId) {
            foreach ($options as $option) {
                if ($option['id'] === $shippingRateId) {
                    return ['feeInPHP' => (float) $option['feeInPHP'], 'label' => $option['label'], 'rateId' => $option['id']];
                }
            }
        }

        $first = $options[0] ?? null;

        return [
            'feeInPHP' => (float) ($first['feeInPHP'] ?? 0),
            'label' => $first['label'] ?? 'Delivery',
            'rateId' => $first['id'] ?? null,
        ];
    }

    /**
     * @param  array<string, mixed>|null  $address
     * @return list<array{id: string|null, label: string, feeInPHP: float, estimatedDays: string|null}>
     */
    private function listShippingOptions(
        string $country,
        float $subtotalInPHP = 0,
        int $totalWeightGrams = 0,
        ?DeliveryMethod $deliveryMethod = null,
        ?array $address = null,
    ): array {
        if ($deliveryMethod === DeliveryMethod::Pickup) {
            return [['id' => null, 'label' => 'Store pickup — free', 'feeInPHP' => 0, 'estimatedDays' => null]];
        }

        $region = ShippingRegion::getShippingRegion($country);
        $settings = $this->platformSettings->getRaw();
        $threshold = (float) $settings->freeShippingMinPHP;
        $zoneCode = ShippingZone::getShippingZoneForAddress($address);

        if ($region === ShippingRegion::REGION_PH && $settings->freeShippingEnabled && $subtotalInPHP >= $threshold) {
            return [['id' => null, 'label' => 'Free delivery', 'feeInPHP' => 0, 'estimatedDays' => '5–7 business days']];
        }

        $rates = ShippingRate::query()
            ->where('isActive', true)
            ->where('region', $region)
            ->orderBy('sortOrder')
            ->get();

        $candidates = $rates->filter(function (ShippingRate $rate) use ($totalWeightGrams) {
            $minOk = $rate->minWeightGrams === null || $totalWeightGrams >= $rate->minWeightGrams;
            $maxOk = $rate->maxWeightGrams === null || $totalWeightGrams <= $rate->maxWeightGrams;

            return $minOk && $maxOk;
        });

        if ($zoneCode) {
            $zoned = $candidates->filter(fn (ShippingRate $rate) => $rate->zone === $zoneCode);
            if ($zoned->isNotEmpty()) {
                $candidates = $zoned;
            } else {
                $candidates = $candidates->filter(fn (ShippingRate $rate) => ! $rate->zone);
            }
        } else {
            $candidates = $candidates->filter(fn (ShippingRate $rate) => ! $rate->zone);
        }

        if ($candidates->isNotEmpty()) {
            return $candidates->map(function (ShippingRate $rate) use ($zoneCode) {
                $label = $zoneCode && $rate->zone === $zoneCode
                    ? "{$rate->label} — ".ShippingZone::getShippingZoneLabel($zoneCode)
                    : $rate->label;

                return [
                    'id' => $rate->id,
                    'label' => $label,
                    'feeInPHP' => (float) $rate->feeInPHP,
                    'estimatedDays' => $rate->estimatedDays,
                ];
            })->values()->all();
        }

        $fallbackFee = $region === ShippingRegion::REGION_PH ? 120.0 : 950.0;
        $fallbackLabel = $region === ShippingRegion::REGION_PH && $zoneCode
            ? 'Delivery — '.ShippingZone::getShippingZoneLabel($zoneCode)
            : ($region === ShippingRegion::REGION_PH ? 'Philippines standard delivery' : 'International standard delivery');

        return [['id' => null, 'label' => $fallbackLabel, 'feeInPHP' => $fallbackFee, 'estimatedDays' => null]];
    }

    private function refreshStaleQuoteFlags(): void
    {
        $settings = $this->platformSettings->getRaw();
        $cutoff = now()->subDays($settings->quoteStaleAlertDays);

        $candidates = Order::query()
            ->whereIn('quoteStatus', [QuoteStatus::PendingReview, QuoteStatus::QuoteSent])
            ->whereNull('quoteStaleAt')
            ->with('supportConversation:id,orderId,updatedAt')
            ->get();

        $staleIds = $candidates->filter(function (Order $order) use ($cutoff) {
            $lastActivity = $order->supportConversation?->updatedAt ?? $order->updatedAt;

            return $lastActivity < $cutoff;
        })->pluck('id')->all();

        if ($staleIds !== []) {
            Order::query()->whereIn('id', $staleIds)->update(['quoteStaleAt' => now()]);
        }
    }

    private function applyFollowUpWhere(Builder $builder): void
    {
        $builder->where('quoteStatus', '!=', QuoteStatus::Cancelled)
            ->where('shippingStatus', '!=', ShippingStatus::Cancelled)
            ->where(function (Builder $q) {
                $q->whereIn('quoteStatus', [QuoteStatus::PendingReview, QuoteStatus::QuoteSent])
                    ->orWhere(function (Builder $q2) {
                        $q2->where('paymentStatus', PaymentStatus::Unpaid)
                            ->whereIn('paymentMethod', [
                                PaymentMethod::SupportAssisted,
                                PaymentMethod::BnplInstallment,
                                PaymentMethod::Cod,
                                PaymentMethod::BankTransfer,
                            ]);
                    });
            });
    }

    /**
     * @param  array{subtotalInPHP: mixed, discountAmountInPHP: mixed, taxAmountInPHP: mixed, shippingFeeInPHP: mixed, installationFeeInPHP: mixed}  $parts
     */
    private function recalculateOrderTotal(array $parts): string
    {
        $settings = $this->platformSettings->getRaw();
        $afterDiscount = Decimal::sub($parts['subtotalInPHP'], $parts['discountAmountInPHP']);
        $merchandiseTotal = $this->pricesAreVatInclusive($settings)
            ? $afterDiscount
            : Decimal::add($afterDiscount, $parts['taxAmountInPHP']);

        return Decimal::add($merchandiseTotal, Decimal::add($parts['shippingFeeInPHP'], $parts['installationFeeInPHP']));
    }

    /**
     * @return array{taxAmountInPHP: string, inclusive: bool}
     */
    private function resolveDomesticVat(string $afterDiscount, object $settings, bool $domestic): array
    {
        if (! $settings->vatEnabled || ! $domestic) {
            return ['taxAmountInPHP' => '0.00', 'inclusive' => false];
        }

        $rate = (string) $settings->vatRatePercent;
        $taxAmountInPHP = Decimal::div(
            Decimal::mul($afterDiscount, $rate),
            Decimal::add('100', $rate),
        );

        return ['taxAmountInPHP' => $taxAmountInPHP, 'inclusive' => true];
    }

    private function pricesAreVatInclusive(object $settings): bool
    {
        return (bool) $settings->vatEnabled;
    }

    private function buildInvoicePdfFromOrder(Order $order): string
    {
        $settings = $this->platformSettings->getRaw();
        $subtotal = $order->subtotalInPHP !== null
            ? (float) $order->subtotalInPHP
            : (float) $order->totalAmountInPHP - (float) $order->shippingFeeInPHP;
        $totalDisplay = $order->currency === Currency::USD
            ? (float) Money::toUsdFromPhp($order->totalAmountInPHP, $order->exchangeRate)
            : (float) $order->totalAmountInPHP;

        return InvoicePdf::generate([
            'storeName' => $settings->storeName ?? 'BNF Asia',
            'storeEmail' => $settings->storeEmail,
            'storePhone' => $settings->storePhone,
            'storeAddress' => $settings->storeAddress,
            'orderNumber' => $order->orderNumber,
            'createdAt' => $order->createdAt,
            'guestEmail' => $order->guestEmail ?? 'Customer',
            'paymentMethod' => $order->paymentMethod->value,
            'paymentStatus' => $order->paymentStatus->value,
            'currency' => $order->currency->value,
            'totalDisplay' => $totalDisplay,
            'subtotalInPHP' => $subtotal,
            'discountAmountInPHP' => (float) ($order->discountAmountInPHP ?? 0),
            'taxAmountInPHP' => (float) ($order->taxAmountInPHP ?? 0),
            'shippingFeeInPHP' => (float) $order->shippingFeeInPHP,
            'installationFeeInPHP' => (float) ($order->installationFeeInPHP ?? 0),
            'shippingAddressLines' => $this->formatAddress($order->shippingAddress ?? []),
            'orderItems' => $order->orderItems->map(fn (OrderItem $item) => [
                'productName' => $item->product?->name ?? 'Product',
                'variantLabel' => $item->variantLabel,
                'quantity' => $item->quantity,
                'totalPriceInPHP' => (float) $item->totalPriceInPHP,
            ])->all(),
        ]);
    }

    /**
     * @param  array<string, mixed>  $address
     * @return list<string>|null
     */
    private function formatAddress(array $address): ?array
    {
        if (empty($address['street1'])) {
            return null;
        }

        $lines = [$address['street1']];
        if (! empty($address['street2'])) {
            $lines[] = $address['street2'];
        }

        $country = strtoupper((string) ($address['country'] ?? ''));
        if (ShippingRegion::isPhilippines($country)) {
            $parts = array_filter([
                $address['barangay'] ?? null,
                $address['city'] ?? null,
                $address['province'] ?? null,
                $address['region'] ?? null,
            ]);
            if ($parts !== []) {
                $lines[] = implode(', ', $parts);
            }
            $lines[] = 'Philippines';
        } else {
            $parts = array_filter([
                $address['city'] ?? null,
                $address['province'] ?? null,
                $address['postalCode'] ?? null,
                $address['country'] ?? null,
            ]);
            if ($parts !== []) {
                $lines[] = implode(', ', $parts);
            }
        }

        return $lines;
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeOrder(Order $order): array
    {
        $address = $order->shippingAddress ?? [];
        $data = $order->toArray();
        $data['subtotalInPHP'] = (float) ($order->subtotalInPHP ?? 0);
        $data['taxAmountInPHP'] = (float) ($order->taxAmountInPHP ?? 0);
        $data['discountAmountInPHP'] = (float) ($order->discountAmountInPHP ?? 0);
        $data['totalAmountInPHP'] = (float) $order->totalAmountInPHP;
        $data['shippingFeeInPHP'] = (float) ($order->shippingFeeInPHP ?? 0);
        $data['installationFeeInPHP'] = (float) ($order->installationFeeInPHP ?? 0);
        $data['installationRequested'] = (bool) $order->installationRequested;
        $data['refundAmountInPHP'] = $order->refundAmountInPHP !== null ? (float) $order->refundAmountInPHP : null;
        $data['exchangeRate'] = (float) $order->exchangeRate;
        $data['quoteStatus'] = ($order->quoteStatus ?? QuoteStatus::None)->value;
        $data['quoteStaleAt'] = $order->quoteStaleAt;
        $data['shippingCountry'] = $address['country'] ?? null;
        $data['shippingCity'] = $address['city'] ?? ($address['province'] ?? null);
        $data['shippingAddressFormatted'] = $this->formatAddress($address);
        $data['customerEmail'] = $order->user?->email ?? $order->guestEmail ?? 'Guest';
        $data['customerPhone'] = $order->guestPhone;
        $data['itemCount'] = $order->relationLoaded('orderItems') ? $order->orderItems->count() : 0;
        $data['orderItems'] = $order->relationLoaded('orderItems')
            ? $order->orderItems->map(fn (OrderItem $item) => array_merge($item->toArray(), [
                'unitPriceInPHP' => (float) $item->unitPriceInPHP,
                'totalPriceInPHP' => (float) $item->totalPriceInPHP,
                'productName' => $item->product?->name ?? 'Product',
            ]))->all()
            : [];
        $data['hasSupportConversation'] = $order->relationLoaded('supportConversation')
            && $order->supportConversation !== null;

        return $data;
    }

    /**
     * @return array<string, mixed>
     */
    private function serializePublicOrder(Order $order): array
    {
        $subtotal = $order->subtotalInPHP !== null
            ? (float) $order->subtotalInPHP
            : (float) $order->totalAmountInPHP - (float) $order->shippingFeeInPHP;

        $timeline = array_merge(
            [['status' => 'ORDER_PLACED', 'message' => 'Order placed', 'at' => $order->createdAt]],
            $order->relationLoaded('shipmentEvents')
                ? $order->shipmentEvents->map(fn ($e) => [
                    'status' => $e->status,
                    'message' => $e->message,
                    'at' => $e->occurredAt,
                    'location' => $e->location,
                ])->all()
                : [],
        );

        return [
            'id' => $order->id,
            'orderNumber' => $order->orderNumber,
            'guestEmail' => $order->guestEmail,
            'guestPhone' => $order->guestPhone,
            'currency' => $order->currency->value,
            'exchangeRate' => (float) $order->exchangeRate,
            'subtotalInPHP' => $subtotal,
            'taxAmountInPHP' => (float) ($order->taxAmountInPHP ?? 0),
            'discountAmountInPHP' => (float) ($order->discountAmountInPHP ?? 0),
            'shippingFeeInPHP' => (float) $order->shippingFeeInPHP,
            'installationFeeInPHP' => (float) ($order->installationFeeInPHP ?? 0),
            'installationRequested' => (bool) $order->installationRequested,
            'totalAmountInPHP' => (float) $order->totalAmountInPHP,
            'promotionCode' => $order->promotionCode,
            'paymentSessionUrl' => $order->paymentSessionUrl,
            'trackingNumber' => $order->trackingNumber,
            'carrier' => $order->carrier,
            'estimatedDeliveryAt' => $order->estimatedDeliveryAt,
            'deliveryMethod' => ($order->deliveryMethod ?? DeliveryMethod::Delivery)->value,
            'timeline' => $timeline,
            'totalDisplay' => $order->currency === Currency::USD
                ? (float) Money::toUsdFromPhp($order->totalAmountInPHP, $order->exchangeRate)
                : (float) $order->totalAmountInPHP,
            'paymentMethod' => $order->paymentMethod->value,
            'paymentStatus' => $order->paymentStatus->value,
            'shippingStatus' => $order->shippingStatus->value,
            'quoteStatus' => ($order->quoteStatus ?? QuoteStatus::None)->value,
            'shippingAddressFormatted' => $this->formatAddress($order->shippingAddress ?? []),
            'customerNote' => $order->customerNote,
            'orderRequests' => $order->relationLoaded('orderRequests')
                ? $order->orderRequests->map(fn (OrderRequest $r) => [
                    'id' => $r->id,
                    'type' => $r->type->value,
                    'status' => $r->status->value,
                    'reason' => $r->reason,
                    'createdAt' => $r->createdAt,
                ])->all()
                : [],
            'createdAt' => $order->createdAt,
            'orderItems' => $order->orderItems->map(fn (OrderItem $item) => [
                'productId' => $item->productId,
                'variantId' => $item->variantId,
                'quantity' => $item->quantity,
                'unitPriceInPHP' => (float) $item->unitPriceInPHP,
                'totalPriceInPHP' => (float) $item->totalPriceInPHP,
                'variantLabel' => $item->variantLabel,
                'productName' => $item->product?->name ?? 'Product',
                'productSlug' => $item->product?->slug,
                'productImage' => $item->product?->images[0] ?? null,
            ])->all(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function paymentSettings(\App\Models\PlatformSetting $settings): array
    {
        return [
            'bnplEnabled' => $settings->bnplEnabled,
            'supportAssistedCheckoutEnabled' => $settings->supportAssistedCheckoutEnabled,
            'codEnabled' => $settings->codEnabled,
            'bankTransferEnabled' => $settings->bankTransferEnabled,
            'paymongoGcashEnabled' => $settings->paymongoGcashEnabled,
            'paymongoMayaEnabled' => $settings->paymongoMayaEnabled,
            'paymongoEnabled' => $settings->paymongoEnabled,
            'stripeEnabled' => $settings->stripeEnabled,
        ];
    }
}
