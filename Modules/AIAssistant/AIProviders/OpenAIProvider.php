<?php

namespace Modules\AIAssistant\AIProviders;

use Modules\AIAssistant\AIProviders\Support\AbstractOpenAICompatibleProvider;
use Modules\AIAssistant\app\DataTransfer\AIProviderCapabilities;

/**
 * Unlike Modules\AI\AIProviders\OpenAIProvider (single-shot content
 * generation, hard-coded to 'gpt-4o' — see architecture doc Part I §5),
 * this provider takes its model from AIChatRequest::$model, which in turn
 * comes from the vendor/platform's configured ai_provider_models row.
 * Nothing here is hard-coded.
 */
class OpenAIProvider extends AbstractOpenAICompatibleProvider
{
    public function getName(): string
    {
        return 'openai';
    }

    protected function defaultBaseUrl(): string
    {
        return 'https://api.openai.com/v1';
    }

    public function getCapabilities(): AIProviderCapabilities
    {
        return new AIProviderCapabilities(
            chat: true,
            systemInstructions: true,
            toolCalling: true,
            structuredOutput: true,
            streaming: true,
            usageReporting: true,
        );
    }
}
