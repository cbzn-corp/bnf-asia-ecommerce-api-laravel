<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Concerns\ResolvesAuthUser;
use App\Http\Controllers\Controller;
use App\Http\Requests\SupportChat\SendSupportMessageRequest;
use App\Services\SupportChatService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SupportChatController extends Controller
{
    use ResolvesAuthUser;

    public function __construct(
        private readonly SupportChatService $supportChatService,
    ) {}

    public function listInbox(): JsonResponse
    {
        return response()->json($this->supportChatService->listInbox());
    }

    public function getMessages(Request $request, string $orderId): JsonResponse
    {
        $user = $this->requireAuthUser($request);

        return response()->json(
            $this->supportChatService->getMessages($orderId, $user, $request->query('after')),
        );
    }

    public function sendMessage(SendSupportMessageRequest $request, string $orderId): JsonResponse
    {
        $user = $this->requireAuthUser($request);

        return response()->json(
            $this->supportChatService->sendMessage($orderId, $user, $request->validated('body')),
        );
    }

    public function resolve(string $orderId): JsonResponse
    {
        return response()->json($this->supportChatService->resolveConversation($orderId));
    }
}
