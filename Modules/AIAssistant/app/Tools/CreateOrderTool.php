<?php

namespace Modules\AIAssistant\app\Tools;

use Modules\AIAssistant\app\Contracts\AIToolInterface;
use Modules\AIAssistant\app\DataTransfer\AIToolResult;
use Modules\AIAssistant\app\DataTransfer\ToolExecutionContext;
use Modules\AIAssistant\app\Models\AIConversation;
use Modules\AIAssistant\app\Services\AICheckoutService;

class CreateOrderTool implements AIToolInterface
{
    public function __construct(private readonly AICheckoutService $checkoutService)
    {
    }

    public function name(): string
    {
        return 'create_order';
    }

    public function description(): string
    {
        return 'Place the order after the customer has explicitly confirmed the summary (items, delivery address, total). Requires start_checkout to have been called first. Every amount is computed by the marketplace, not by you.';
    }

    public function parameterSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'address_id' => ['type' => 'integer', 'description' => 'An id from start_checkout\'s existing_addresses'],
                'new_address' => [
                    'type' => 'object',
                    'properties' => [
                        'contact_person_name' => ['type' => 'string'],
                        'phone' => ['type' => 'string'],
                        'address' => ['type' => 'string'],
                        'city' => ['type' => 'string'],
                        'zip' => ['type' => 'string'],
                        'country' => ['type' => 'string'],
                    ],
                    'required' => ['contact_person_name', 'phone', 'address', 'city', 'country'],
                ],
                'payment_method' => ['type' => 'string', 'enum' => ['cash_on_delivery']],
                'order_note' => ['type' => 'string'],
            ],
        ];
    }

    public function execute(array $arguments, ToolExecutionContext $context): AIToolResult
    {
        $conversation = AIConversation::find($context->conversationId);
        if (!$conversation) {
            return AIToolResult::fail('Conversation not found.');
        }

        try {
            $summary = $this->checkoutService->confirmOrder($conversation, $context, $arguments);
        } catch (\RuntimeException $exception) {
            return AIToolResult::fail($exception->getMessage());
        }

        return AIToolResult::ok($summary);
    }
}
