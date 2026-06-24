<?php

declare(strict_types=1);

namespace App\Services\Roles;

use App\Config\Permissions;
use App\Models\Role;
use App\Services\Audit\AuditService;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class RolesService
{
    public function __construct(
        private readonly AuditService $auditService,
    ) {}

    /**
     * @return array{permissions: list<string>, groups: list<array<string, mixed>>}
     */
    public function getPermissionCatalog(): array
    {
        return [
            'permissions' => Permissions::ALL_PERMISSION_KEYS,
            'groups' => Permissions::permissionGroups(),
        ];
    }

    /**
     * @return \Illuminate\Database\Eloquent\Collection<int, Role>
     */
    public function findAll()
    {
        return Role::query()
            ->withCount('users')
            ->orderByDesc('isSystem')
            ->orderBy('name')
            ->get();
    }

    /**
     * @return \Illuminate\Database\Eloquent\Collection<int, Role>
     */
    public function findStaffRoles()
    {
        return Role::query()
            ->where('isStaff', true)
            ->orderByDesc('isSystem')
            ->orderBy('name')
            ->get([
                'id',
                'key',
                'name',
                'description',
                'isSystem',
                'permissions',
            ]);
    }

    public function findOne(string $id): Role
    {
        $role = Role::query()
            ->withCount('users')
            ->find($id);

        if ($role === null) {
            throw new NotFoundHttpException('Role not found.');
        }

        return $role;
    }

    /**
     * @param  array{key: string, name: string, description?: string|null, permissions?: list<string>}  $dto
     */
    public function create(array $dto, string $actorEmail): Role
    {
        $key = strtoupper(preg_replace('/[^A-Z0-9_]/', '_', trim($dto['key'])) ?? '');

        if ($key === '') {
            throw new BadRequestHttpException('Role key is required.');
        }

        if (in_array($key, [Permissions::CUSTOMER_ROLE_KEY, 'ADMIN'], true)) {
            throw new BadRequestHttpException('This role key is reserved.');
        }

        if (Role::query()->where('key', $key)->exists()) {
            throw new BadRequestHttpException('Role key already exists.');
        }

        $permissions = Permissions::sanitizePermissions($dto['permissions'] ?? []);

        $role = Role::create([
            'key' => $key,
            'name' => trim($dto['name']),
            'description' => isset($dto['description']) ? (trim($dto['description']) ?: null) : null,
            'isSystem' => false,
            'isStaff' => true,
            'permissions' => $permissions,
        ]);

        $role->loadCount('users');

        $this->auditService->log([
            'userEmail' => $actorEmail,
            'action' => 'ROLE_CREATED',
            'entity' => 'Role',
            'entityId' => $role->id,
            'details' => [
                'key' => $role->key,
                'name' => $role->name,
                'permissions' => $role->permissions,
            ],
        ]);

        return $role;
    }

    /**
     * @param  array{name?: string, description?: string|null, permissions?: list<string>}  $dto
     */
    public function update(string $id, array $dto, string $actorEmail): Role
    {
        $role = $this->findOne($id);

        if ($role->key === Permissions::CUSTOMER_ROLE_KEY) {
            throw new BadRequestHttpException('Customer role cannot be modified.');
        }

        $permissions = array_key_exists('permissions', $dto)
            ? Permissions::sanitizePermissions($dto['permissions'] ?? [])
            : null;

        if ($role->key === 'ADMIN' && $permissions !== null) {
            $missingAdmin = array_values(array_filter(
                Permissions::ALL_PERMISSION_KEYS,
                static fn (string $key): bool => ! in_array($key, $permissions, true),
            ));

            if ($missingAdmin !== []) {
                throw new BadRequestHttpException('Super Admin must retain all permissions.');
            }
        }

        $data = [];

        if (array_key_exists('name', $dto) && $dto['name'] !== null) {
            $data['name'] = trim($dto['name']);
        }

        if (array_key_exists('description', $dto)) {
            $data['description'] = trim((string) $dto['description']) ?: null;
        }

        if ($permissions !== null) {
            $data['permissions'] = $permissions;
        }

        $role->update($data);
        $role->refresh()->loadCount('users');

        $this->auditService->log([
            'userEmail' => $actorEmail,
            'action' => 'ROLE_UPDATED',
            'entity' => 'Role',
            'entityId' => $id,
            'details' => [...$dto, 'permissions' => $role->permissions],
        ]);

        return $role;
    }

    /**
     * @return array{ok: true}
     */
    public function remove(string $id, string $actorEmail): array
    {
        $role = $this->findOne($id);

        if ($role->isSystem) {
            throw new BadRequestHttpException('System roles cannot be deleted.');
        }

        if ($role->users_count > 0) {
            throw new BadRequestHttpException('Reassign staff users before deleting this role.');
        }

        $role->delete();

        $this->auditService->log([
            'userEmail' => $actorEmail,
            'action' => 'ROLE_DELETED',
            'entity' => 'Role',
            'entityId' => $id,
            'details' => [
                'key' => $role->key,
                'name' => $role->name,
            ],
        ]);

        return ['ok' => true];
    }
}
