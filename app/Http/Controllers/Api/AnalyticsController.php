<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\Analytics\AnalyticsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AnalyticsController extends Controller
{
    public function __construct(
        private readonly AnalyticsService $analyticsService,
    ) {}

    public function dashboard(Request $request): JsonResponse
    {
        return response()->json($this->analyticsService->getDashboardAnalytics([
            'days' => $request->query('days') !== null ? (int) $request->query('days') : null,
            'from' => $request->query('from'),
            'to' => $request->query('to'),
        ]));
    }
}
