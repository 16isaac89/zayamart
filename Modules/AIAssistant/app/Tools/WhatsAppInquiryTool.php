<?php

namespace Modules\AIAssistant\app\Tools;

use Modules\AIAssistant\app\Contracts\AIToolInterface;
use Modules\AIAssistant\app\DataTransfer\AIToolResult;
use Modules\AIAssistant\app\DataTransfer\ToolExecutionContext;
use Modules\AIAssistant\app\Services\WhatsAppLinkService;

/**
 * A "click to chat" wa.me link for a customer who wants to ask the vendor
 * something directly — a custom request, negotiation, or anything outside
 * what the other tools can answer — rather than placing an order. Needs
 * only the vendor's phone number, never a Cloud API credential.
 */
class WhatsAppInquiryTool implements AIToolInterface
{
    public function __construct(private readonly WhatsAppLinkService $whatsAppLinkService)
    {
    }

    public function name(): string
    {
        return 'get_whatsapp_inquiry_link';
    }

    public function description(): string
    {
        return "Generate a WhatsApp link the customer can click to message this vendor directly, for a question you can't answer with the other tools (custom requests, negotiation, anything not about placing an order). Opens the customer's own WhatsApp app or web with a message pre-filled — they still have to tap send themselves.";
    }

    public function parameterSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'topic' => ['type' => 'string', 'description' => "Short summary of what the customer wants to ask, used to pre-fill the WhatsApp message. Omit if there isn't one."],
            ],
        ];
    }

    public function execute(array $arguments, ToolExecutionContext $context): AIToolResult
    {
        $link = $this->whatsAppLinkService->inquiryLink($context->sellerId, $arguments['topic'] ?? null);

        if (!$link) {
            return AIToolResult::fail('This vendor has no phone number on file — ask the customer to use "Talk to a human" instead.');
        }

        return AIToolResult::ok(['whatsapp_link' => $link]);
    }
}
