<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Users\CreateStaffRequest;
use App\Http\Requests\Users\UpdateCustomerRequest;
use App\Http\Requests\Users\UpdateStaffRequest;
use App\Services\Users\UsersService;
use App\Support\Auth\AuthUser;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class UsersController extends Controller
{
    public function __construct(
        private readonly UsersService $usersService,
    ) {}

    public function findCustomers(Request $request): JsonResponse
    {
        return response()->json($this->usersService->findCustomers($request->query('search')));
    }

    public function findCustomer(string $id): JsonResponse
    {
        return response()->json($this->usersService->findCustomerById($id));
    }

    public function updateCustomer(UpdateCustomerRequest $request, string $id): JsonResponse
    {
        return response()->json($this->usersService->updateCustomer(
            $id,
            $request->validated(),
            $this->authUser($request)->email,
        ));
    }

    public function findAll(Request $request): JsonResponse
    {
        return response()->json($this->usersService->findAll(
            $request->query('roleId'),
            $request->query('search'),
        ));
    }

    public function createStaff(CreateStaffRequest $request): JsonResponse
    {
        return response()->json($this->usersService->createStaff(
            $request->validated(),
            $this->authUser($request)->email,
        ));
    }

    public function updateStaff(UpdateStaffRequest $request, string $id): JsonResponse
    {
        return response()->json($this->usersService->updateStaff(
            $id,
            $request->validated(),
            $this->authUser($request)->email,
        ));
    }

    private function authUser(Request $request): AuthUser
    {
        /** @var AuthUser $authUser */
        $authUser = $request->attributes->get('authUser');

        return $authUser;
    }
}
