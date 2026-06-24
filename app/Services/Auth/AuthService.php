<?php

declare(strict_types=1);

namespace App\Services\Auth;

use App\Config\Permissions;
use App\Enums\PaymentStatus;
use App\Enums\ShippingStatus;
use App\Models\Order;
use App\Models\Role;
use App\Models\User;
use App\Services\Audit\AuditService;
use App\Services\Email\EmailService;
use App\Services\Reviews\ReviewsService;
use App\Services\Settings\PlatformSettingsService;
use App\Support\Auth\AuthUser;
use App\Support\Config\AppUrls;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use PHPOpenSourceSaver\JWTAuth\Exceptions\JWTException;
use PHPOpenSourceSaver\JWTAuth\Facades\JWTAuth;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\UnauthorizedHttpException;

class AuthService
{
    private const TOKEN_TTL_MINUTES = 480;

    private const PASSWORD_RESET_TTL_MINUTES = 60;

    public function __construct(
        private readonly AuditService $auditService,
        private readonly EmailService $emailService,
        private readonly PlatformSettingsService $platformSettings,
        private readonly ReviewsService $reviewsService,
    ) {}

    /**
     * @param  array{email: string, password: string}  $dto
     * @return array{accessToken: string, user: array<string, mixed>}
     */
    public function login(array $dto): array
    {
        $user = User::query()
            ->where('email', strtolower($dto['email']))
            ->with('role')
            ->first();

        if ($user === null || ! $user->role?->isStaff) {
            throw new UnauthorizedHttpException('', 'Invalid credentials');
        }

        if (! $user->isActive) {
            throw new UnauthorizedHttpException('', 'Account is disabled');
        }

        if (! Hash::check($dto['password'], $user->passwordHash)) {
            throw new UnauthorizedHttpException('', 'Invalid credentials');
        }

        return $this->issueToken($user);
    }

    /**
     * @param  array{email: string, password: string}  $dto
     * @return array{accessToken: string, user: array<string, mixed>}
     */
    public function customerRegister(array $dto): array
    {
        $email = strtolower($dto['email']);
        $existing = User::query()->where('email', $email)->first();

        if ($existing !== null) {
            throw new BadRequestHttpException('Email already registered.');
        }

        $customerRole = Role::query()->where('key', Permissions::CUSTOMER_ROLE_KEY)->first();

        if ($customerRole === null) {
            throw new BadRequestHttpException('Customer role is not configured.');
        }

        $user = User::create([
            'email' => $email,
            'passwordHash' => Hash::make($dto['password']),
            'roleId' => $customerRole->id,
        ]);
        $user->load('role');

        return $this->issueToken($user);
    }

    /**
     * @param  array{email: string, password: string}  $dto
     * @return array{accessToken: string, user: array<string, mixed>}
     */
    public function customerLogin(array $dto): array
    {
        $user = User::query()
            ->where('email', strtolower($dto['email']))
            ->with('role')
            ->first();

        if ($user === null || $user->role?->key !== Permissions::CUSTOMER_ROLE_KEY) {
            throw new UnauthorizedHttpException('Invalid credentials');
        }

        if (! $user->isActive) {
            throw new UnauthorizedHttpException('Account is disabled');
        }

        if (! Hash::check($dto['password'], $user->passwordHash)) {
            throw new UnauthorizedHttpException('Invalid credentials');
        }

        return $this->issueToken($user);
    }

    public function validateUser(string $userId): ?AuthUser
    {
        $user = User::query()
            ->where('id', $userId)
            ->with('role')
            ->first();

        if ($user === null || ! $user->isActive) {
            return null;
        }

        return $this->toAuthUser($user);
    }

    public function verifyToken(string $token): ?AuthUser
    {
        try {
            $payload = JWTAuth::setToken($token)->getPayload();
            $userId = $payload->get('id') ?? $payload->get('sub');

            if ($userId === null || $userId === '') {
                return null;
            }

            return $this->validateUser((string) $userId);
        } catch (JWTException) {
            return null;
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function getStaffProfile(string $userId): array
    {
        $user = User::query()
            ->where('id', $userId)
            ->with('role')
            ->first();

        if ($user === null || ! $user->role?->isStaff) {
            throw new UnauthorizedHttpException('', 'Staff access required');
        }

        return [
            ...$this->toAuthUser($user)->toArray(),
            'isActive' => $user->isActive,
            'createdAt' => $user->createdAt?->toISOString(),
            'updatedAt' => $user->updatedAt?->toISOString(),
        ];
    }

    /**
     * @return array{user: array<string, mixed>, platformSettings: array<string, mixed>, pendingReviewCount: int}
     */
    public function getAdminBootstrap(string $userId): array
    {
        $user = $this->getStaffProfile($userId);
        $permissions = is_array($user['permissions'] ?? null) ? $user['permissions'] : [];
        $roleKey = (string) ($user['roleKey'] ?? '');

        $pendingReviewCount = 0;
        if (
            $roleKey === Permissions::ADMIN_ROLE_KEY
            || Permissions::hasPermission($permissions, 'reviews.manage')
        ) {
            $pendingReviewCount = $this->reviewsService->countPending();
        }

        return [
            'user' => $user,
            'platformSettings' => $this->platformSettings->getAdminConfig(),
            'pendingReviewCount' => $pendingReviewCount,
        ];
    }

    /**
     * @return array{message: string}
     */
    public function requestCustomerPasswordReset(string $email): array
    {
        $normalized = strtolower(trim($email));
        $user = User::query()
            ->where('email', $normalized)
            ->with('role')
            ->first();

        $message = 'If an account exists with that email, you will receive reset instructions shortly.';

        if ($user === null || $user->role?->key !== Permissions::CUSTOMER_ROLE_KEY || ! $user->isActive) {
            return ['message' => $message];
        }

        $token = JWTAuth::manager()->encode(
            JWTAuth::factory()
                ->setTTL(self::PASSWORD_RESET_TTL_MINUTES)
                ->customClaims(['purpose' => 'password-reset'])
                ->sub($user->getJWTIdentifier())
                ->make()
        )->get();

        $resetLink = AppUrls::getStorefrontUrl().'/reset-password?token='.rawurlencode($token);

        $this->emailService->sendPasswordResetEmail([
            'to' => $user->email,
            'resetLink' => $resetLink,
        ]);

        return ['message' => $message];
    }

    /**
     * @return array{message: string}
     */
    public function resetCustomerPassword(string $token, string $newPassword): array
    {
        try {
            $payload = JWTAuth::setToken($token)->getPayload();
            $purpose = $payload->get('purpose');
            $userId = $payload->get('sub');
        } catch (JWTException) {
            throw new BadRequestHttpException('Invalid or expired reset link.');
        }

        if ($purpose !== 'password-reset' || $userId === null || $userId === '') {
            throw new BadRequestHttpException('Invalid or expired reset link.');
        }

        $user = User::query()
            ->where('id', (string) $userId)
            ->with('role')
            ->first();

        if ($user === null || $user->role?->key !== Permissions::CUSTOMER_ROLE_KEY || ! $user->isActive) {
            throw new BadRequestHttpException('Invalid or expired reset link.');
        }

        $user->update([
            'passwordHash' => Hash::make($newPassword),
        ]);

        return ['message' => 'Password updated. You can sign in with your new password.'];
    }

    /**
     * @return array{updated: true}
     */
    public function changeStaffPassword(string $userId, string $currentPassword, string $newPassword): array
    {
        $user = User::query()
            ->where('id', $userId)
            ->with('role')
            ->first();

        if ($user === null || ! $user->role?->isStaff) {
            throw new UnauthorizedHttpException('', 'Staff access required');
        }

        if (! $user->isActive) {
            throw new UnauthorizedHttpException('', 'Account is disabled');
        }

        if (! Hash::check($currentPassword, $user->passwordHash)) {
            throw new BadRequestHttpException('Current password is incorrect.');
        }

        if ($currentPassword === $newPassword) {
            throw new BadRequestHttpException('New password must be different from the current password.');
        }

        $user->update([
            'passwordHash' => Hash::make($newPassword),
        ]);

        $this->auditService->log([
            'userEmail' => $user->email,
            'action' => 'account.password_change',
            'entity' => 'User',
            'entityId' => $user->id,
        ]);

        return ['updated' => true];
    }

    /**
     * @return array{accessToken: string, user: array<string, mixed>, message: string}
     */
    public function registerFromOrder(string $orderNumber, string $email, string $password, string $name): array
    {
        $normalized = strtolower(trim($email));
        $order = Order::query()->where('orderNumber', $orderNumber)->first();

        if ($order === null) {
            throw new BadRequestHttpException('Order not found.');
        }

        if ($order->guestEmail === null || strtolower($order->guestEmail) !== $normalized) {
            throw new BadRequestHttpException('Email does not match this order.');
        }

        if ($order->userId !== null) {
            throw new BadRequestHttpException('This order is already linked to an account. Please sign in.');
        }

        $existing = User::query()->where('email', $normalized)->first();

        if ($existing !== null) {
            throw new BadRequestHttpException('Email already registered. Please sign in.');
        }

        $customerRole = Role::query()->where('key', Permissions::CUSTOMER_ROLE_KEY)->first();

        if ($customerRole === null) {
            throw new BadRequestHttpException('Customer role is not configured.');
        }

        $user = DB::transaction(function () use ($normalized, $password, $customerRole, $order): User {
            $created = User::create([
                'email' => $normalized,
                'passwordHash' => Hash::make($password),
                'roleId' => $customerRole->id,
            ]);

            $order->update(['userId' => $created->id]);

            return $created->load('role');
        });

        $token = $this->issueToken($user);

        return [
            ...$token,
            'message' => 'Welcome, '.trim($name).'! Your order '.$orderNumber.' is now linked to your account.',
        ];
    }

    /**
     * @return array{user: array<string, mixed>}
     */
    public function getCustomerProfile(string $userId): array
    {
        $user = User::query()
            ->where('id', $userId)
            ->with('role')
            ->first();

        if ($user === null || $user->role?->key !== Permissions::CUSTOMER_ROLE_KEY) {
            throw new UnauthorizedHttpException('', 'Customer access required');
        }

        return [
            'user' => [
                ...$this->toAuthUser($user)->toArray(),
                'marketingOptIn' => $user->marketingOptIn,
            ],
        ];
    }

    /**
     * @param  array{marketingOptIn?: bool}  $dto
     * @return array{user: array<string, mixed>}
     */
    public function updateCustomerProfile(string $userId, array $dto): array
    {
        $user = User::query()
            ->where('id', $userId)
            ->with('role')
            ->first();

        if ($user === null || $user->role?->key !== Permissions::CUSTOMER_ROLE_KEY) {
            throw new UnauthorizedHttpException('', 'Customer access required');
        }

        $data = [];

        if (array_key_exists('marketingOptIn', $dto)) {
            $data['marketingOptIn'] = $dto['marketingOptIn'];
        }

        $user->update($data);
        $user->load('role');

        return [
            'user' => [
                ...$this->toAuthUser($user)->toArray(),
                'marketingOptIn' => $user->marketingOptIn,
            ],
        ];
    }

    /**
     * @return array{message: string}
     */
    public function changeCustomerPassword(string $userId, string $currentPassword, string $newPassword): array
    {
        $user = User::query()
            ->where('id', $userId)
            ->with('role')
            ->first();

        if ($user === null || $user->role === null || $user->role->isStaff) {
            throw new UnauthorizedHttpException('', 'Customer access required');
        }

        if (! $user->isActive) {
            throw new UnauthorizedHttpException('', 'Account is disabled');
        }

        if (! Hash::check($currentPassword, $user->passwordHash)) {
            throw new BadRequestHttpException('Current password is incorrect.');
        }

        if ($currentPassword === $newPassword) {
            throw new BadRequestHttpException('New password must be different from the current password.');
        }

        $user->update([
            'passwordHash' => Hash::make($newPassword),
        ]);

        return ['message' => 'Password updated successfully.'];
    }

    public function toAuthUser(User $user): AuthUser
    {
        $role = $user->role;

        return new AuthUser(
            id: $user->id,
            email: $user->email,
            roleId: $user->roleId,
            roleKey: (string) $role?->key,
            roleName: (string) $role?->name,
            isStaff: (bool) $role?->isStaff,
            permissions: Permissions::sanitizePermissions(array_values($role?->permissions ?? [])),
        );
    }

    /**
     * @return array{accessToken: string, user: array<string, mixed>}
     */
    private function issueToken(User $user): array
    {
        $user->loadMissing('role');
        $authUser = $this->toAuthUser($user);

        $accessToken = JWTAuth::fromUser($user);

        return [
            'accessToken' => $accessToken,
            'user' => $authUser->toArray(),
        ];
    }
}
