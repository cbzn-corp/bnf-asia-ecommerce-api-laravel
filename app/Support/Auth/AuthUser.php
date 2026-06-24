<?php

declare(strict_types=1);

namespace App\Support\Auth;

readonly class AuthUser
{
    /**
     * @param  list<string>  $permissions
     */
    public function __construct(
        public string $id,
        public string $email,
        public string $roleId,
        public string $roleKey,
        public string $roleName,
        public bool $isStaff,
        public array $permissions,
    ) {}

    /**
     * @return array{
     *     id: string,
     *     email: string,
     *     roleId: string,
     *     roleKey: string,
     *     roleName: string,
     *     isStaff: bool,
     *     permissions: list<string>
     * }
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'email' => $this->email,
            'roleId' => $this->roleId,
            'roleKey' => $this->roleKey,
            'roleName' => $this->roleName,
            'isStaff' => $this->isStaff,
            'permissions' => $this->permissions,
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            id: (string) $data['id'],
            email: (string) $data['email'],
            roleId: (string) $data['roleId'],
            roleKey: (string) $data['roleKey'],
            roleName: (string) $data['roleName'],
            isStaff: (bool) $data['isStaff'],
            permissions: array_values($data['permissions'] ?? []),
        );
    }
}
