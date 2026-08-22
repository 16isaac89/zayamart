<?php

namespace Modules\AIAssistant\app\Services;

use App\Jobs\DispatchVendorNotificationJob;
use App\Models\VendorNotification;
use Modules\AIAssistant\app\Models\AIConversation;

/**
 * Human handoff — brief §9/§37. Detection is deliberately a server-side
 * keyword match against the raw customer message, not an LLM-reported
 * "confidence score": "Server-side rules should control mandatory
 * escalation conditions."
 */
class HandoffService
{
    public function __construct(private readonly AuditLogger $auditLogger)
    {
    }

    public function shouldRequestHuman(AIConversation $conversation, string $customerText): bool
    {
        $phrases = array_merge(
            config('aiassistant.default_handoff_phrases', []),
            $conversation->agent?->settings?->handoff_phrases ?? [],
        );

        $haystack = mb_strtolower($customerText);
        foreach ($phrases as $phrase) {
            if ($phrase !== '' && str_contains($haystack, mb_strtolower($phrase))) {
                return true;
            }
        }

        return false;
    }

    public function requestHuman(AIConversation $conversation): void
    {
        if ($conversation->support_status === AIConversation::SUPPORT_HUMAN_REQUESTED
            || $conversation->support_status === AIConversation::SUPPORT_HUMAN_ACTIVE) {
            return;
        }

        $conversation->update([
            'support_status' => AIConversation::SUPPORT_HUMAN_REQUESTED,
            'human_requested_at' => now(),
        ]);

        $this->auditLogger->log(
            actorType: 'system',
            actorId: null,
            sellerId: $conversation->seller_id,
            eventType: 'human_handoff_requested',
            description: "Conversation #{$conversation->id} requested a human agent.",
        );

        // Queued deliberately — this method runs synchronously inside the
        // live customer chat turn (ConversationController), and an FCM
        // push HTTP call must never add latency there. See the
        // notification architecture report, §15.
        DispatchVendorNotificationJob::dispatch(
            $conversation->seller_id,
            VendorNotification::TYPE_CUSTOMER_NEEDS_HELP,
            'Customer needs help',
            "A customer in conversation #{$conversation->id} asked to speak with a person.",
            'ai_conversation',
            $conversation->id,
            route('vendor.ai-assistant.inbox.show', $conversation->id),
            ['conversation_id' => $conversation->id],
        );
    }

    public function takeOver(AIConversation $conversation, int $sellerId): void
    {
        $conversation->update([
            'support_status' => AIConversation::SUPPORT_HUMAN_ACTIVE,
            'human_agent_seller_id' => $sellerId,
            'human_taken_over_at' => now(),
        ]);

        $this->auditLogger->log(
            actorType: 'seller',
            actorId: $sellerId,
            sellerId: $conversation->seller_id,
            eventType: 'human_takeover',
            description: "Seller #{$sellerId} took over conversation #{$conversation->id}.",
        );
    }

    public function returnToAi(AIConversation $conversation, int $sellerId): void
    {
        $conversation->update([
            'support_status' => AIConversation::SUPPORT_ACTIVE,
            'human_returned_at' => now(),
        ]);

        $this->auditLogger->log(
            actorType: 'seller',
            actorId: $sellerId,
            sellerId: $conversation->seller_id,
            eventType: 'conversation_returned_to_ai',
            description: "Seller #{$sellerId} returned conversation #{$conversation->id} to AI.",
        );
    }
}
