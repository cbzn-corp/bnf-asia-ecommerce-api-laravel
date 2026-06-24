<?php

declare(strict_types=1);

namespace App\Services\Users;

use App\Config\Permissions;
use App\Enums\PaymentStatus;
use App\Enums\ShippingStatus;
use App\Models\Role;
use App\Models\User;
use App\Services\Audit\AuditService;
use Illuminate\Support\Facades\Hash;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class UsersService
{
    public function __construct(
        private readonly AuditService $auditService,
    ) {}

    /**
     * @return list<array<string, mixed>>
     */
    public function findAll(?string $roleId = null, ?string $search = null): array
    {
        $query = User::query()
            ->whereHas('role', static fn ($q) => $q->where('isStaff', true))
            ->with(['role:id,key,name,isStaff'])
            ->withCount('orders')
            ->orderByDesc('createdAt');

        if ($roleId !== null && $roleId !== '') {
            $query->where('roleId', $roleId);
        }

        if ($search !== null && trim($search) !== '') {
            $query->where('email', 'ilike', '%'.trim($search).'%');
        }

        return $query
            ->get([
                'id',
                'email',
                'roleId',
                'isActive',
                'createdAt',
                'updatedAt',
            ])
            ->map(static function (User $row): array {
                return [
                    'id' => $row->id,
                    'email' => $row->email,
                    'roleId' => $row->roleId,
                    'role' => $row->role?->key,
                    'isActive' => $row->isActive,
                    'createdAt' => $row->createdAt,
                    'updatedAt' => $row->updatedAt,
                    '_count' => ['orders' => $row->orders_count],
                ];
            })
            ->all();
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function findCustomers(?string $search = null): array
    {
        $query = User::query()
            ->whereHas('role', static fn ($q) => $q->where('key', Permissions::CUSTOMER_ROLE_KEY))
            ->with(['role:id,key,name'])
            ->withCount(['orders', 'reviews'])
            ->orderByDesc('createdAt');

        if ($search !== null && trim($search) !== '') {
            $query->where('email', 'ilike', '%'.trim($search).'%');
        }

        $users = $query->get([
            'id',
            'email',
            'roleId',
            'isActive',
            'createdAt',
        ]);

        if ($users->isEmpty()) {
            return [];
        }

        $userIds = $users->pluck('id')->all();

        $orderStats = \App\Models\Order::query()
            ->selectRaw('"userId", SUM(CASE WHEN "paymentStatus" = ? THEN "totalAmountInPHP" ELSE 0 END) as total_spent, MAX("createdAt") as last_order_at', [PaymentStatus::Paid->value])
            ->whereIn('userId', $userIds)
            ->groupBy('userId')
            ->get()
            ->keyBy('userId');

        return $users->map(function (User $user) use ($orderStats): array {
            $stats = $orderStats->get($user->id);

            return [
                'id' => $user->id,
                'email' => $user->email,
                'roleId' => $user->roleId,
                'role' => $user->role?->key,
                'isActive' => $user->isActive,
                'createdAt' => $user->createdAt,
                '_count' => [
                    'orders' => $user->orders_count,
                    'reviews' => $user->reviews_count,
                ],
                'totalSpentInPHP' => $stats ? (float) ($stats->total_spent ?? 0) : 0,
                'lastOrderAt' => $stats?->last_order_at
                    ? \Illuminate\Support\Carbon::parse($stats->last_order_at)->toISOString()
                    : null,
            ];
        })->all();
    }

    /**
     * @return array<string, mixed>
     */
    public function findCustomerById(string $id): array
    {
        $user = User::query()
            ->where('id', $id)
            ->with([
                'role:id,key,name',
                'addresses' => static fn ($q) => $q->orderByDesc('isDefault')->orderByDesc('createdAt'),
                'reviews' => static fn ($q) => $q
                    ->orderByDesc('createdAt')
                    ->take(10)
                    ->with(['product:id,name']),
                'orders' => static fn ($q) => $q
                    ->orderByDesc('createdAt')
                    ->take(50)
                    ->with(['orderItems.product:id,name']),
            ])
            ->withCount(['orders', 'reviews'])
            ->first([
                'id',
                'email',
                'roleId',
                'isActive',
                'createdAt',
                'updatedAt',
            ]);

        if ($user === null) {
            throw new NotFoundHttpException('Customer not found.');
        }

        if ($user->role?->key !== Permissions::CUSTOMER_ROLE_KEY) {
            throw new BadRequestHttpException('Not a customer account.');
        }

        $paidAgg = \App\Models\Order::query()
            ->where('userId', $id)
            ->where('paymentStatus', PaymentStatus::Paid->value)
            ->selectRaw('SUM("totalAmountInPHP") as total_spent, COUNT(*) as paid_count')
            ->first();

        $pendingOrders = \App\Models\Order::query()
            ->where('userId', $id)
            ->where(static function ($q): void {
                $q->where('paymentStatus', PaymentStatus::Unpaid->value)
                    ->orWhere(static function ($q2): void {
                        $q2->where('paymentStatus', PaymentStatus::Paid->value)
                            ->whereIn('shippingStatus', [
                                ShippingStatus::Pending->value,
                                ShippingStatus::Processing->value,
                            ]);
                    });
            })
            ->count();

        $lastOrder = \App\Models\Order::query()
            ->where('userId', $id)
            ->orderByDesc('createdAt')
            ->first(['createdAt']);

        $totalSpentInPHP = (float) ($paidAgg->total_spent ?? 0);
        $paidOrders = (int) ($paidAgg->paid_count ?? 0);

        return [
            'id' => $user->id,
            'email' => $user->email,
            'role' => $user->role?->key,
            'isActive' => $user->isActive,
            'createdAt' => $user->createdAt,
            'updatedAt' => $user->updatedAt,
            'stats' => [
                'totalOrders' => $user->orders_count,
                'paidOrders' => $paidOrders,
                'pendingOrders' => $pendingOrders,
                'totalSpentInPHP' => $totalSpentInPHP,
                'averageOrderValue' => $paidOrders > 0 ? $totalSpentInPHP / $paidOrders : 0,
                'lastOrderAt' => $lastOrder?->createdAt?->toISOString(),
                'reviewCount' => $user->reviews_count,
            ],
            'addresses' => $user->addresses,
            'reviews' => $user->reviews->map(static fn ($review): array => [
                'id' => $review->id,
                'productName' => $review->product?->name,
                'rating' => $review->rating,
                'comment' => $review->comment,
                'isApproved' => $review->isApproved,
                'createdAt' => $review->createdAt,
            ])->all(),
            'orders' => $user->orders->map(fn ($order): array => $this->serializeCustomerOrder($order))->all(),
        ];
    }

    /**
     * @param  array{isActive?: bool}  $dto
     * @return array<string, mixed>
     */
    public function updateCustomer(string $id, array $dto, string $changedBy): array
    {
        $user = User::query()
            ->where('id', $id)
            ->with('role')
            ->first();

        if ($user === null) {
            throw new NotFoundHttpException('Customer not found.');
        }

        if ($user->role?->key !== Permissions::CUSTOMER_ROLE_KEY) {
            throw new BadRequestHttpException('Not a customer account.');
        }

        $data = [];

        if (array_key_exists('isActive', $dto)) {
            $data['isActive'] = $dto['isActive'];
        }

        $user->update($data);
        $user->load('role:id,key,name');

        $this->auditService->log([
            'userEmail' => $changedBy,
            'action' => 'CUSTOMER_UPDATED',
            'entity' => 'User',
            'entityId' => $user->id,
            'details' => ['isActive' => $user->isActive],
        ]);

        return [
            'id' => $user->id,
            'email' => $user->email,
            'roleId' => $user->roleId,
            'role' => $user->role?->key,
            'isActive' => $user->isActive,
            'updatedAt' => $user->updatedAt,
        ];
    }

    /**
     * @param  array{email: string, password: string, roleId: string}  $dto
     * @return array<string, mixed>
     */
    public function createStaff(array $dto, string $invitedBy): array
    {
        $email = strtolower($dto['email']);

        if (User::query()->where('email', $email)->exists()) {
            throw new BadRequestHttpException('Email already exists.');
        }

        $role = Role::query()->find($dto['roleId']);

        if ($role === null || ! $role->isStaff) {
            throw new BadRequestHttpException('Staff must be assigned a staff role.');
        }

        $user = User::create([
            'email' => $email,
            'passwordHash' => Hash::make($dto['password']),
            'roleId' => $role->id,
        ]);

        $user->load('role:id,key,name');

        $this->auditService->log([
            'userEmail' => $invitedBy,
            'action' => 'STAFF_CREATED',
            'entity' => 'User',
            'entityId' => $user->id,
            'details' => [
                'email' => $user->email,
                'roleId' => $user->roleId,
                'roleKey' => $user->role?->key,
            ],
        ]);

        return [
            'id' => $user->id,
            'email' => $user->email,
            'roleId' => $user->roleId,
            'role' => $user->role?->key,
            'isActive' => $user->isActive,
            'createdAt' => $user->createdAt,
        ];
    }

    /**
     * @param  array{roleId?: string, isActive?: bool, password?: string}  $dto
     * @return array<string, mixed>
     */
    public function updateStaff(string $id, array $dto, string $changedBy): array
    {
        $user = User::query()
            ->where('id', $id)
            ->with('role')
            ->first();

        if ($user === null) {
            throw new NotFoundHttpException('User not found.');
        }

        if (! $user->role?->isStaff) {
            throw new BadRequestHttpException('Use customer endpoints for customer accounts.');
        }

        $data = [];

        if (array_key_exists('roleId', $dto) && $dto['roleId'] !== null) {
            $role = Role::query()->find($dto['roleId']);

            if ($role === null || ! $role->isStaff) {
                throw new BadRequestHttpException('Staff must be assigned a staff role.');
            }

            $data['roleId'] = $role->id;
        }

        if (array_key_exists('isActive', $dto)) {
            $data['isActive'] = $dto['isActive'];
        }

        if (! empty($dto['password'])) {
            $data['passwordHash'] = Hash::make($dto['password']);
        }

        $user->update($data);
        $user->load('role:id,key,name');

        $this->auditService->log([
            'userEmail' => $changedBy,
            'action' => 'STAFF_UPDATED',
            'entity' => 'User',
            'entityId' => $id,
            'details' => $dto,
        ]);

        return [
            'id' => $user->id,
            'email' => $user->email,
            'roleId' => $user->roleId,
            'role' => $user->role?->key,
            'isActive' => $user->isActive,
            'updatedAt' => $user->updatedAt,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeCustomerOrder(\App\Models\Order $order): array
    {
        $address = is_array($order->shippingAddress) ? $order->shippingAddress : [];

        return [
            'id' => $order->id,
            'orderNumber' => $order->orderNumber,
            'customerEmail' => '',
            'shippingCountry' => $address['country'] ?? null,
            'shippingCity' => $address['city'] ?? $address['province'] ?? null,
            'currency' => $order->currency,
            'totalAmountInPHP' => (float) $order->totalAmountInPHP,
            'paymentMethod' => $order->paymentMethod,
            'paymentStatus' => $order->paymentStatus,
            'shippingStatus' => $order->shippingStatus,
            'itemCount' => $order->orderItems?->count() ?? 0,
            'createdAt' => $order->createdAt,
        ];
    }
}
