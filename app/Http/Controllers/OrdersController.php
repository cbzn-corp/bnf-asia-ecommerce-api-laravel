<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ResolvesAuthUser;
use App\Services\OrdersService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Validation\Rule;

class OrdersController extends Controller
{
    use ResolvesAuthUser;

    public function __construct(
        private readonly OrdersService $ordersService,
    ) {}

    public function preview(Request $request)
    {
        $data = $request->validate([
            'items' => ['required', 'array', 'min:1'],
            'items.*.productId' => ['required', 'string'],
            'items.*.variantId' => ['nullable', 'string'],
            'items.*.quantity' => ['required', 'integer', 'min:1'],
            'shippingAddress' => ['nullable', 'array'],
            'promotionCode' => ['nullable', 'string'],
            'deliveryMethod' => ['nullable', Rule::enum(\App\Enums\DeliveryMethod::class)],
            'userId' => ['nullable', 'string'],
            'bundleId' => ['nullable', 'string'],
            'installationRequested' => ['nullable', 'boolean'],
            'shippingRateId' => ['nullable', 'string'],
            'shippingFeeInPHP' => ['nullable', 'numeric', 'min:0'],
            'pickupLocationId' => ['nullable', 'string'],
            'forManualOrder' => ['nullable', 'boolean'],
        ]);

        return response()->json($this->ordersService->preview($data));
    }

    public function track(Request $request)
    {
        $data = $request->validate([
            'orderNumber' => ['required', 'string'],
            'email' => ['required', 'email'],
        ]);

        return response()->json($this->ordersService->trackOrder($data['orderNumber'], $data['email']));
    }

    public function checkout(Request $request)
    {
        $data = $request->validate([
            'guestEmail' => ['required', 'email'],
            'guestPhone' => ['nullable', 'string'],
            'userId' => ['nullable', 'string'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.productId' => ['required', 'string'],
            'items.*.variantId' => ['nullable', 'string'],
            'items.*.quantity' => ['required', 'integer', 'min:1'],
            'shippingAddress' => ['required', 'array'],
            'paymentMethod' => ['required', Rule::enum(\App\Enums\PaymentMethod::class)],
            'promotionCode' => ['nullable', 'string'],
            'deliveryMethod' => ['nullable', Rule::enum(\App\Enums\DeliveryMethod::class)],
            'pickupLocationId' => ['nullable', 'string'],
            'bundleId' => ['nullable', 'string'],
            'installationRequested' => ['nullable', 'boolean'],
            'customerNote' => ['nullable', 'string'],
            'shippingRateId' => ['nullable', 'string'],
        ]);

        return response()->json($this->ordersService->checkout($data, $this->authUser($request)));
    }

    public function myOrders(Request $request)
    {
        $user = $this->requireAuthUser($request);

        return response()->json($this->ordersService->findMyOrders($user->id));
    }

    public function myOrder(Request $request, string $orderNumber)
    {
        $user = $this->requireAuthUser($request);

        return response()->json($this->ordersService->findMyOrderByNumber($user->id, $orderNumber));
    }

    public function downloadMyInvoice(Request $request, string $orderNumber): Response
    {
        $user = $this->requireAuthUser($request);
        $pdf = $this->ordersService->getInvoicePdfForCustomer($user->id, $orderNumber);

        return response($pdf, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => "attachment; filename=\"invoice-{$orderNumber}.pdf\"",
        ]);
    }

    public function cancelRequest(Request $request, string $orderNumber)
    {
        $user = $this->requireAuthUser($request);
        $data = $request->validate(['reason' => ['required', 'string']]);

        return response()->json(
            $this->ordersService->requestCancel($user->id, $orderNumber, $data['reason']),
        );
    }

    public function returnRequest(Request $request, string $orderNumber)
    {
        $user = $this->requireAuthUser($request);
        $data = $request->validate(['reason' => ['required', 'string']]);

        return response()->json(
            $this->ordersService->requestReturn($user->id, $orderNumber, $data['reason']),
        );
    }

    public function getStats()
    {
        return response()->json($this->ordersService->getDashboardStats());
    }

    public function createManual(Request $request)
    {
        $staff = $this->requireAuthUser($request);
        $data = $request->validate([
            'guestEmail' => ['required', 'email'],
            'guestPhone' => ['nullable', 'string'],
            'userId' => ['nullable', 'string'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.productId' => ['required', 'string'],
            'items.*.variantId' => ['nullable', 'string'],
            'items.*.quantity' => ['required', 'integer', 'min:1'],
            'shippingAddress' => ['required', 'array'],
            'paymentMethod' => ['required', Rule::enum(\App\Enums\PaymentMethod::class)],
            'promotionCode' => ['nullable', 'string'],
            'deliveryMethod' => ['nullable', Rule::enum(\App\Enums\DeliveryMethod::class)],
            'pickupLocationId' => ['nullable', 'string'],
            'paymentStatus' => ['nullable', Rule::enum(\App\Enums\PaymentStatus::class)],
            'shippingStatus' => ['nullable', Rule::enum(\App\Enums\ShippingStatus::class)],
            'shippingFeeInPHP' => ['nullable', 'numeric', 'min:0'],
            'markAsPaid' => ['nullable', 'boolean'],
            'sendConfirmationEmail' => ['nullable', 'boolean'],
            'internalNote' => ['nullable', 'string'],
        ]);

        return response()->json($this->ordersService->createManualOrder($data, $staff->email));
    }

    public function findAll(Request $request)
    {
        return response()->json($this->ordersService->findAll($request->query()));
    }

    public function downloadAdminInvoice(string $id): Response
    {
        $result = $this->ordersService->getInvoicePdfForAdmin($id);

        return response($result['pdf'], 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => "attachment; filename=\"invoice-{$result['orderNumber']}.pdf\"",
        ]);
    }

    public function findOne(string $id)
    {
        return response()->json($this->ordersService->findOne($id));
    }

    public function update(Request $request, string $id)
    {
        $staff = $this->requireAuthUser($request);
        $data = $request->validate([
            'paymentStatus' => ['nullable', Rule::enum(\App\Enums\PaymentStatus::class)],
            'shippingStatus' => ['nullable', Rule::enum(\App\Enums\ShippingStatus::class)],
            'trackingNumber' => ['nullable', 'string'],
            'carrier' => ['nullable', 'string'],
            'estimatedDeliveryAt' => ['nullable', 'date'],
            'shippingFeeInPHP' => ['nullable', 'numeric', 'min:0'],
            'installationFeeInPHP' => ['nullable', 'numeric', 'min:0'],
        ]);

        return response()->json($this->ordersService->update($id, $data, $staff->email));
    }

    public function cancelQuote(Request $request, string $id)
    {
        $staff = $this->requireAuthUser($request);

        return response()->json($this->ordersService->cancelQuote($id, $staff->email));
    }

    public function sendPaymentReminder(Request $request, string $id)
    {
        $staff = $this->requireAuthUser($request);

        return response()->json($this->ordersService->sendPaymentReminder($id, $staff->email));
    }

    public function updateQuote(Request $request, string $id)
    {
        $staff = $this->requireAuthUser($request);
        $data = $request->validate([
            'items' => ['nullable', 'array'],
            'items.*.productId' => ['required_with:items', 'string'],
            'items.*.variantId' => ['nullable', 'string'],
            'items.*.quantity' => ['required_with:items', 'integer', 'min:1'],
            'shippingFeeInPHP' => ['nullable', 'numeric', 'min:0'],
            'installationFeeInPHP' => ['nullable', 'numeric', 'min:0'],
            'installationRequested' => ['nullable', 'boolean'],
            'discountAmountInPHP' => ['nullable', 'numeric', 'min:0'],
            'taxAmountInPHP' => ['nullable', 'numeric', 'min:0'],
            'quoteStatus' => ['nullable', Rule::enum(\App\Enums\QuoteStatus::class)],
            'paymentMethod' => ['nullable', Rule::enum(\App\Enums\PaymentMethod::class)],
            'notifyCustomer' => ['nullable', 'boolean'],
        ]);

        return response()->json($this->ordersService->updateQuote($id, $data, $staff->email));
    }

    public function addNote(Request $request, string $id)
    {
        $staff = $this->requireAuthUser($request);
        $data = $request->validate(['body' => ['required', 'string']]);

        return response()->json($this->ordersService->addNote($id, $data['body'], $staff->email));
    }

    public function processRefund(Request $request, string $id)
    {
        $staff = $this->requireAuthUser($request);
        $data = $request->validate([
            'refundAmountInPHP' => ['required', 'numeric', 'min:0'],
            'refundReason' => ['nullable', 'string'],
            'refundStatus' => ['required', Rule::enum(\App\Enums\RefundStatus::class)],
        ]);

        return response()->json($this->ordersService->processRefund($id, $data, $staff->email));
    }

    public function resolveOrderRequest(Request $request, string $id, string $requestId)
    {
        $staff = $this->requireAuthUser($request);
        $data = $request->validate([
            'action' => ['required', Rule::in(['approve', 'reject'])],
            'staffNote' => ['nullable', 'string'],
        ]);

        return response()->json(
            $this->ordersService->resolveOrderRequest(
                $id,
                $requestId,
                $data['action'],
                $staff->email,
                $data['staffNote'] ?? null,
            ),
        );
    }
}
