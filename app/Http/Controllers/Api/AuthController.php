<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\ChangePasswordRequest;
use App\Http\Requests\Auth\CustomerLoginRequest;
use App\Http\Requests\Auth\ForgotPasswordRequest;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Requests\Auth\RegisterFromOrderRequest;
use App\Http\Requests\Auth\RegisterRequest;
use App\Http\Requests\Auth\ResetPasswordRequest;
use App\Http\Requests\Auth\UpdateCustomerProfileRequest;
use App\Services\Auth\AuthService;
use App\Support\Auth\AuthUser;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AuthController extends Controller
{
    public function __construct(
        private readonly AuthService $authService,
    ) {}

    public function login(LoginRequest $request): JsonResponse
    {
        return response()->json($this->authService->login($request->validated()));
    }

    public function customerRegister(RegisterRequest $request): JsonResponse
    {
        return response()->json($this->authService->customerRegister($request->validated()));
    }

    public function registerFromOrder(RegisterFromOrderRequest $request): JsonResponse
    {
        $dto = $request->validated();

        return response()->json($this->authService->registerFromOrder(
            $dto['orderNumber'],
            $dto['email'],
            $dto['password'],
            $dto['name'],
        ));
    }

    public function customerLogin(CustomerLoginRequest $request): JsonResponse
    {
        return response()->json($this->authService->customerLogin($request->validated()));
    }

    public function customerForgotPassword(ForgotPasswordRequest $request): JsonResponse
    {
        return response()->json($this->authService->requestCustomerPasswordReset($request->validated('email')));
    }

    public function customerResetPassword(ResetPasswordRequest $request): JsonResponse
    {
        $dto = $request->validated();

        return response()->json($this->authService->resetCustomerPassword($dto['token'], $dto['password']));
    }

    public function me(Request $request): JsonResponse
    {
        $authUser = $this->authUser($request);

        return response()->json([
            'user' => $this->authService->getStaffProfile($authUser->id),
        ]);
    }

    public function adminBootstrap(Request $request): JsonResponse
    {
        $authUser = $this->authUser($request);

        return response()->json($this->authService->getAdminBootstrap($authUser->id));
    }

    public function changePassword(ChangePasswordRequest $request): JsonResponse
    {
        $authUser = $this->authUser($request);
        $dto = $request->validated();

        return response()->json($this->authService->changeStaffPassword(
            $authUser->id,
            $dto['currentPassword'],
            $dto['newPassword'],
        ));
    }

    public function customerMe(Request $request): JsonResponse
    {
        return response()->json($this->authService->getCustomerProfile($this->authUser($request)->id));
    }

    public function updateCustomerProfile(UpdateCustomerProfileRequest $request): JsonResponse
    {
        return response()->json($this->authService->updateCustomerProfile(
            $this->authUser($request)->id,
            $request->validated(),
        ));
    }

    public function customerChangePassword(ChangePasswordRequest $request): JsonResponse
    {
        $authUser = $this->authUser($request);
        $dto = $request->validated();

        return response()->json($this->authService->changeCustomerPassword(
            $authUser->id,
            $dto['currentPassword'],
            $dto['newPassword'],
        ));
    }

    private function authUser(Request $request): AuthUser
    {
        /** @var AuthUser $authUser */
        $authUser = $request->attributes->get('authUser');

        return $authUser;
    }
}
