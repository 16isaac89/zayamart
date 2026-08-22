<?php

namespace Modules\AIAssistant\app\Tools;

use App\Models\ShippingAddress;
use Modules\AIAssistant\app\Contracts\AIToolInterface;
use Modules\AIAssistant\app\DataTransfer\AIToolResult;
use Modules\AIAssistant\app\DataTransfer\ToolExecutionContext;
use Modules\AIAssistant\app\Models\AIConversation;

/**
 * Flips the conversation into Checkout Mode and reports what is still
 * missing. This is the only way ai_conversations.status becomes
 * 'awaiting_confirmation' — create_order refuses to run unless it is
 * already in that state, so the state transition (not an LLM-asserted
 * "confirm: true" argument) is what actually gates order creation. See
 * architecture doc Part II §12/§18.
 */
class StartCheckoutTool implements AIToolInterface
{
    public function name(): string
    {
        return 'start_checkout';
    }

    public function description(): string
    {
        return 'Call this once the customer has clearly said they want to order. Returns the cart summary and what information (delivery address, etc.) is still needed before create_order can be called.';
    }

    public function parameterSchema(): array
    {
        return ['type' => 'object', 'properties' => new \stdClass()];
    }

    public function execute(array $arguments, ToolExecutionContext $context): AIToolResult
    {
        $cartItems = GetCartTool::sellerCartQuery($context)->get();

        if ($cartItems->isEmpty()) {
            return AIToolResult::fail('Cart is empty for this vendor — add at least one product before checking out.');
        }

        $ownerId = $context->isGuest ? $context->guestId : $context->customerId;
        $addresses = ShippingAddress::where('customer_id', $ownerId)
            ->where('is_guest', $context->isGuest ? 1 : 0)
            ->get(['id', 'contact_person_name', 'address', 'city', 'phone']);

        $conversation = AIConversation::find($context->conversationId);
        $conversation?->update(['mode' => 'checkout', 'status' => 'awaiting_confirmation']);

        return AIToolResult::ok([
            'cart_item_count' => $cartItems->count(),
            'existing_addresses' => $addresses->toArray(),
            'needs_new_address' => $addresses->isEmpty(),
            'next_step' => $addresses->isEmpty()
                ? 'Collect contact_person_name, phone, address, city, country from the customer, then call create_order with new_address.'
                : 'Ask the customer to confirm which existing address to deliver to (or provide a new one), then call create_order.',
        ]);
    }
}
