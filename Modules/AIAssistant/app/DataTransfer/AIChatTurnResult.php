<?php

namespace Modules\AIAssistant\app\DataTransfer;

/**
 * The structured envelope every chat turn returns to the customer channel
 * (brief §12). Deliberately NOT built by asking the LLM to emit a JSON
 * envelope itself — that's fragile across providers and hard to validate.
 * Instead ConversationService derives $type/$data from whichever
 * marketplace tool actually ran during the turn, so the "structured
 * response" is built from data Laravel already validated (the tool's own
 * result), never from AI-authored JSON. See architecture doc Part III §12.
 */
final class AIChatTurnResult
{
    public const TYPE_TEXT = 'text';
    public const TYPE_PRODUCT = 'product';
    public const TYPE_PRODUCT_LIST = 'product_list';
    public const TYPE_CART = 'cart';
    public const TYPE_CHECKOUT = 'checkout';
    public const TYPE_ORDER_SUMMARY = 'order_summary';
    public const TYPE_CONFIRMATION = 'confirmation';
    public const TYPE_HANDOFF = 'handoff';
    public const TYPE_ERROR = 'error';

    public function __construct(
        public readonly ?string $reply,
        public readonly string $type,
        public readonly array $data,
        public readonly string $supportStatus,
        public readonly string $handlingMode,
        public readonly int $conversationId,
    ) {
    }

    public function toArray(): array
    {
        return [
            'reply' => $this->reply,
            'type' => $this->type,
            'data' => $this->data,
            'support_status' => $this->supportStatus,
            'handling_mode' => $this->handlingMode,
            'conversation_id' => $this->conversationId,
        ];
    }
}
