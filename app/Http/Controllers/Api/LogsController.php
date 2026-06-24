<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\Logs\LogsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LogsController extends Controller
{
    public function __construct(
        private readonly LogsService $logsService,
    ) {}

    public function findPaymentLogs(Request $request): JsonResponse
    {
        return response()->json(
            $this->logsService->findPaymentLogs($request->query('orderNumber')),
        );
    }
}
