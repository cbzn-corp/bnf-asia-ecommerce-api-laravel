<?php

declare(strict_types=1);

namespace App\Services\Audit;

use App\Models\AuditLog;

class AuditService
{
    /**
     * @param  array{
     *     userEmail: string,
     *     action: string,
     *     entity: string,
     *     entityId?: string|null,
     *     details?: array<string, mixed>|null
     * }  $params
     */
    public function log(array $params): AuditLog
    {
        return AuditLog::create([
            'userEmail' => $params['userEmail'],
            'action' => $params['action'],
            'entity' => $params['entity'],
            'entityId' => $params['entityId'] ?? null,
            'details' => $params['details'] ?? null,
        ]);
    }

    /**
     * @param  array{entity?: string, limit?: int, page?: int}|null  $params
     * @return array{
     *     data: \Illuminate\Database\Eloquent\Collection<int, AuditLog>,
     *     meta: array{total: int, page: int, limit: int, totalPages: int}
     * }
     */
    public function findAll(?array $params = null): array
    {
        $limit = min($params['limit'] ?? 50, 100);
        $page = $params['page'] ?? 1;

        $query = AuditLog::query()->orderByDesc('createdAt');

        if (! empty($params['entity'])) {
            $query->where('entity', $params['entity']);
        }

        $total = (clone $query)->count();
        $data = $query
            ->skip(($page - 1) * $limit)
            ->take($limit)
            ->get();

        return [
            'data' => $data,
            'meta' => [
                'total' => $total,
                'page' => $page,
                'limit' => $limit,
                'totalPages' => (int) ceil($total / $limit),
            ],
        ];
    }
}
