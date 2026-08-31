<?php

namespace Modules\AIAssistant\app\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Shop;
use App\Utils\Helpers;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\AIAssistant\app\DataTransfer\ToolExecutionContext;
use Modules\AIAssistant\app\Http\Requests\SendMessageRequest;
use Modules\AIAssistant\app\Models\AIAgent;
use Modules\AIAssistant\app\Models\AIConversation;
use Modules\AIAssistant\app\Models\AIMessage;
use Modules\AIAssistant\app\Services\ConversationService;
use Modules\AIAssistant\app\Services\HandoffService;

/**
 * The one HTTP entrypoint for the storefront chat widget (web session) and
 * the API/app channel (Sanctum/Passport bearer token) alike — both resolve
 * the same way RestAPI\v1\OrderController's checkout already does (see
 * architecture doc Part II §10), so this single controller serves both
 * without duplicating the customer/guest-resolution logic.
 */
class ConversationController extends Controller
{
    public function __construct(
        private readonly ConversationService $conversationService,
        private readonly HandoffService $handoffService,
    ) {
    }

    public function send(SendMessageRequest $request, string $shopSlug): JsonResponse
    {
        [$agent, $conversation, $error] = $this->resolve($request, $shopSlug);
        if ($error) {
            return $error;
        }

        $isGuest = $conversation->isGuest();
        $context = new ToolExecutionContext(
            sellerId: $agent->seller_id,
            aiAgentId: $agent->id,
            conversationId: $conversation->id,
            customerId: $conversation->customer_id,
            guestId: $conversation->guest_id,
            isGuest: $isGuest,
            request: $request,
        );

        $result = $this->conversationService->handleUserMessage($conversation, $request->input('message'), $context);

        return response()->json($result->toArray());
    }

    /**
     * Polled by the widget while a human agent may be involved — human
     * replies arrive asynchronously, not as a direct response to the
     * customer's own POST (brief §9; BROADCAST_DRIVER=log by default in
     * this project, so polling — not a live socket push — is the honest
     * delivery mechanism here).
     */
    public function messages(Request $request, string $shopSlug, int $conversationId): JsonResponse
    {
        [$agent, $conversation, $error] = $this->resolve($request, $shopSlug, $conversationId);
        if ($error) {
            return $error;
        }

        return response()->json([
            'support_status' => $conversation->support_status,
            'messages' => $conversation->messages()
                ->where('sender_type', '!=', AIMessage::SENDER_SYSTEM)
                ->get()
                ->map(fn (AIMessage $m) => [
                    'id' => $m->id,
                    'sender_type' => $m->sender_type,
                    'sender_name' => $m->sender_name,
                    'content' => $m->content,
                    'created_at' => $m->created_at?->toIso8601String(),
                ]),
        ]);
    }

    public function requestHuman(Request $request, string $shopSlug, int $conversationId): JsonResponse
    {
        [$agent, $conversation, $error] = $this->resolve($request, $shopSlug, $conversationId);
        if ($error) {
            return $error;
        }

        $this->handoffService->requestHuman($conversation);

        return response()->json(['support_status' => $conversation->fresh()->support_status]);
    }

    /**
     * The customer's own way back to the AI after a handoff — see
     * HandoffService::resumeAiForCustomer()'s docblock.
     */
    public function resumeAi(Request $request, string $shopSlug, int $conversationId): JsonResponse
    {
        [$agent, $conversation, $error] = $this->resolve($request, $shopSlug, $conversationId);
        if ($error) {
            return $error;
        }

        $this->handoffService->resumeAiForCustomer($conversation);

        return response()->json(['support_status' => $conversation->fresh()->support_status]);
    }

    /**
     * @return array{0: ?AIAgent, 1: ?AIConversation, 2: ?JsonResponse}
     */
    private function resolve(Request $request, string $shopSlug, ?int $conversationId = null): array
    {
        $shop = Shop::where('slug', $shopSlug)->first();
        if (!$shop) {
            return [null, null, response()->json(['message' => 'Shop not found.'], 404)];
        }

        $agent = AIAgent::where('seller_id', $shop->seller_id)->where('status', true)->first();
        if (!$agent) {
            return [null, null, response()->json(['message' => 'AI assistant is not available for this shop.'], 404)];
        }

        $user = Helpers::getCustomerInformation($request);
        $isGuest = $user === 'offline';
        $guestId = $isGuest ? (int)(session('guest_id') ?? $request->input('guest_id') ?? 0) : null;
        $customerId = $isGuest ? null : (int)$user->id;

        if ($isGuest && !$guestId) {
            return [null, null, response()->json(['message' => 'A guest session is required to chat.'], 422)];
        }

        if ($conversationId) {
            $conversation = AIConversation::where('id', $conversationId)
                ->where('seller_id', $agent->seller_id)
                ->where('customer_id', $customerId)
                ->where('guest_id', $isGuest ? $guestId : null)
                ->first();

            if (!$conversation) {
                return [null, null, response()->json(['message' => 'Conversation not found.'], 404)];
            }

            return [$agent, $conversation, null];
        }

        $conversation = AIConversation::firstOrCreate(
            [
                'ai_agent_id' => $agent->id,
                'seller_id' => $agent->seller_id,
                'customer_id' => $customerId,
                'guest_id' => $isGuest ? $guestId : null,
                'status' => 'active',
            ],
            [
                'channel' => $request->is('api/*') ? 'api' : 'web',
                'mode' => 'shopping',
                'support_status' => AIConversation::SUPPORT_ACTIVE,
                'started_at' => now(),
            ],
        );

        // "status = active" is part of the lookup key above deliberately —
        // once a conversation moves to awaiting_confirmation/confirmed it is
        // left alone and a fresh conversation starts for further shopping,
        // rather than reusing a row that already has (or is mid-way through)
        // a confirmed order.

        return [$agent, $conversation, null];
    }
}
