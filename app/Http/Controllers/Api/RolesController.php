<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Roles\CreateRoleRequest;
use App\Http\Requests\Roles\UpdateRoleRequest;
use App\Services\Roles\RolesService;
use App\Support\Auth\AuthUser;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class RolesController extends Controller
{
    public function __construct(
        private readonly RolesService $rolesService,
    ) {}

    public function getPermissionCatalog(): JsonResponse
    {
        return response()->json($this->rolesService->getPermissionCatalog());
    }

    public function findStaffRoles(): JsonResponse
    {
        return response()->json($this->rolesService->findStaffRoles());
    }

    public function findAll(): JsonResponse
    {
        return response()->json($this->rolesService->findAll());
    }

    public function findOne(string $id): JsonResponse
    {
        return response()->json($this->rolesService->findOne($id));
    }

    public function create(CreateRoleRequest $request): JsonResponse
    {
        return response()->json($this->rolesService->create(
            $request->validated(),
            $this->authUser($request)->email,
        ));
    }

    public function update(UpdateRoleRequest $request, string $id): JsonResponse
    {
        return response()->json($this->rolesService->update(
            $id,
            $request->validated(),
            $this->authUser($request)->email,
        ));
    }

    public function remove(Request $request, string $id): JsonResponse
    {
        return response()->json($this->rolesService->remove($id, $this->authUser($request)->email));
    }

    private function authUser(Request $request): AuthUser
    {
        /** @var AuthUser $authUser */
        $authUser = $request->attributes->get('authUser');

        return $authUser;
    }
}
