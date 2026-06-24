<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\Audit\AuditService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AuditController extends Controller
{
    public function __construct(
        private readonly AuditService $auditService,
    ) {}

    public function findAll(Request $request): JsonResponse
    {
        return response()->json($this->auditService->findAll([
            'entity' => $request->query('entity'),
            'page' => $request->query('page') !== null ? (int) $request->query('page') : null,
            'limit' => $request->query('limit') !== null ? (int) $request->query('limit') : null,
        ]));
    }
}
