<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Concerns\ResolvesAuthUser;
use App\Http\Controllers\Controller;
use App\Http\Requests\CartEvents\RecordCartAddEventRequest;
use App\Services\CartEventsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CartEventsController extends Controller
{
    use ResolvesAuthUser;

    public function __construct(
        private readonly CartEventsService $cartEventsService,
    ) {}

    public function record(RecordCartAddEventRequest $request): JsonResponse
    {
        $user = $this->requireAuthUser($request);

        return response()->json(
            $this->cartEventsService->recordAdd($user->id, $request->validated()),
        );
    }

    public function report(Request $request): JsonResponse
    {
        return response()->json(
            $this->cartEventsService->report(
                $request->query('from') ? (string) $request->query('from') : null,
                $request->query('to') ? (string) $request->query('to') : null,
            ),
        );
    }
}
