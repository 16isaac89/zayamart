<?php

namespace Modules\AIAssistant\app\DataTransfer;

use Illuminate\Http\Request;

/**
 * What a tool is authorized to act as — resolved by ConversationController
 * from the real Laravel auth/session state of the request handling this
 * chat turn, never from LLM-supplied tool arguments. Every tool must scope
 * its queries by sellerId/customerId/guestId from here. See architecture
 * doc Part II §4/§11.
 */
final class ToolExecutionContext
{
    public function __construct(
        public readonly int $sellerId,
        public readonly int $aiAgentId,
        public readonly int $conversationId,
        public readonly ?int $customerId,
        public readonly ?int $guestId,
        public readonly bool $isGuest,
        public readonly Request $request,
    ) {
    }
}
