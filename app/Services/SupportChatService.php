<?php

declare(strict_types=1);

namespace App\Services;

use App\Config\Permissions;
use App\Enums\ConversationStatus;
use App\Enums\MessageSenderType;
use App\Enums\QuoteStatus;
use App\Models\Order;
use App\Models\SupportConversation;
use App\Models\SupportMessage;
use App\Services\Settings\PlatformSettingsService;
use App\Support\Auth\AuthUser;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class SupportChatService
{
    public function __construct(
        private readonly PlatformSettingsService $platformSettings,
    ) {}

    public function isEnabled(): bool
    {
        return (bool) $this->platformSettings->getRaw()->customerChatEnabled;
    }

    public function isStaff(AuthUser $user): bool
    {
        return $user->roleKey !== Permissions::CUSTOMER_ROLE_KEY;
    }

    /**
     * @return Order
     */
    public function assertOrderAccess(string $orderId, AuthUser $user): Order
    {
        $order = Order::query()
            ->select(['id', 'userId', 'paymentMethod', 'quoteStatus'])
            ->find($orderId);

        if (! $order) {
            throw new NotFoundHttpException('Order not found.');
        }

        if ($this->isStaff($user)) {
            return $order;
        }

        if ($order->userId !== $user->id) {
            throw new AccessDeniedHttpException('You do not have access to this order.');
        }

        return $order;
    }

    public function assertCustomerChatEnabled(AuthUser $user): void
    {
        if ($this->isStaff($user) || $this->isEnabled()) {
            return;
        }

        throw new AccessDeniedHttpException('Customer chat is currently unavailable.');
    }

    public function ensureConversation(string $orderId): SupportConversation
    {
        return SupportConversation::query()->firstOrCreate(['orderId' => $orderId]);
    }

    public function createWelcomeMessage(string $orderId): SupportMessage
    {
        if (! $this->isEnabled()) {
            throw new AccessDeniedHttpException('Customer chat is currently unavailable.');
        }

        $conversation = $this->ensureConversation($orderId);
        $existing = SupportMessage::query()
            ->where('conversationId', $conversation->id)
            ->where('senderType', MessageSenderType::System)
            ->first();

        if ($existing) {
            return $existing;
        }

        return SupportMessage::query()->create([
            'conversationId' => $conversation->id,
            'senderType' => MessageSenderType::System,
            'body' => 'Thanks for your order! Our team is reviewing your delivery and installation details. '.
                'Reply here with any questions — we will send an updated quote when ready.',
        ]);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function listInbox(): array
    {
        $conversations = SupportConversation::query()
            ->where('status', ConversationStatus::Open)
            ->with([
                'order:id,orderNumber,guestEmail,quoteStatus,paymentMethod,totalAmountInPHP,createdAt,userId',
                'order.user:id,email',
                'messages' => fn ($q) => $q->orderByDesc('createdAt')->limit(1),
            ])
            ->orderByDesc('updatedAt')
            ->get();

        return $conversations->map(function (SupportConversation $conversation) {
            $unreadCount = SupportMessage::query()
                ->where('conversationId', $conversation->id)
                ->where('senderType', MessageSenderType::Customer)
                ->whereNull('readAt')
                ->count();

            $lastMessage = $conversation->messages->first();
            $order = $conversation->order;

            return [
                'id' => $conversation->id,
                'orderId' => $conversation->orderId,
                'status' => $conversation->status->value,
                'updatedAt' => $conversation->updatedAt,
                'unreadCount' => $unreadCount,
                'lastMessage' => $lastMessage ? [
                    'body' => $lastMessage->body,
                    'senderType' => $lastMessage->senderType->value,
                    'createdAt' => $lastMessage->createdAt,
                ] : null,
                'order' => [
                    'id' => $order->id,
                    'orderNumber' => $order->orderNumber,
                    'guestEmail' => $order->guestEmail,
                    'quoteStatus' => $order->quoteStatus->value,
                    'paymentMethod' => $order->paymentMethod,
                    'totalAmountInPHP' => (float) $order->totalAmountInPHP,
                    'createdAt' => $order->createdAt,
                    'customerEmail' => $order->user?->email ?? $order->guestEmail,
                ],
            ];
        })->all();
    }

    /**
     * @return array{conversationId: string|null, status?: string, messages: list<array<string, mixed>>}
     */
    public function getMessages(string $orderId, AuthUser $user, ?string $after = null): array
    {
        $this->assertOrderAccess($orderId, $user);
        $this->assertCustomerChatEnabled($user);

        $conversation = SupportConversation::query()
            ->where('orderId', $orderId)
            ->with(['messages' => fn ($q) => $q->orderBy('createdAt')])
            ->first();

        if (! $conversation) {
            return ['conversationId' => null, 'messages' => []];
        }

        if ($this->isStaff($user)) {
            SupportMessage::query()
                ->where('conversationId', $conversation->id)
                ->where('senderType', MessageSenderType::Customer)
                ->whereNull('readAt')
                ->update(['readAt' => now()]);
        }

        $messages = $conversation->messages;

        if ($after) {
            $anchor = $messages->firstWhere('id', $after);
            $messages = $anchor
                ? $messages->filter(fn ($m) => $m->createdAt > $anchor->createdAt)->values()
                : $messages;
        }

        return [
            'conversationId' => $conversation->id,
            'status' => $conversation->status->value,
            'messages' => $messages->map(fn (SupportMessage $m) => [
                'id' => $m->id,
                'senderType' => $m->senderType->value,
                'senderUserId' => $m->senderUserId,
                'body' => $m->body,
                'readAt' => $m->readAt,
                'createdAt' => $m->createdAt,
            ])->all(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function sendMessage(string $orderId, AuthUser $user, string $body): array
    {
        $order = $this->assertOrderAccess($orderId, $user);
        $this->assertCustomerChatEnabled($user);

        if (in_array($order->quoteStatus, [QuoteStatus::Cancelled, QuoteStatus::Accepted], true)) {
            throw new AccessDeniedHttpException('This conversation is closed.');
        }

        $conversation = $this->ensureConversation($orderId);
        if ($conversation->status === ConversationStatus::Resolved) {
            throw new AccessDeniedHttpException('This conversation is resolved.');
        }

        $senderType = $this->isStaff($user) ? MessageSenderType::Staff : MessageSenderType::Customer;

        $message = DB::transaction(function () use ($conversation, $senderType, $user, $body) {
            $message = SupportMessage::query()->create([
                'conversationId' => $conversation->id,
                'senderType' => $senderType,
                'senderUserId' => $user->id,
                'body' => trim($body),
            ]);
            $conversation->touch();

            return $message;
        });

        return [
            'id' => $message->id,
            'senderType' => $message->senderType->value,
            'senderUserId' => $message->senderUserId,
            'body' => $message->body,
            'createdAt' => $message->createdAt,
            'orderId' => $orderId,
        ];
    }

    public function addSystemMessage(string $orderId, string $body): SupportMessage
    {
        $conversation = $this->ensureConversation($orderId);
        $message = SupportMessage::query()->create([
            'conversationId' => $conversation->id,
            'senderType' => MessageSenderType::System,
            'body' => $body,
        ]);
        $conversation->touch();

        return $message;
    }

    public function resolveConversation(string $orderId): SupportConversation
    {
        $conversation = SupportConversation::query()->where('orderId', $orderId)->first();
        if (! $conversation) {
            throw new NotFoundHttpException('Conversation not found.');
        }

        $conversation->update(['status' => ConversationStatus::Resolved]);

        return $conversation->fresh();
    }
}
