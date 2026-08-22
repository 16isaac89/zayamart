<?php

namespace Modules\AIAssistant\AIProviders;

use Modules\AIAssistant\AIProviders\Support\AbstractOpenAICompatibleProvider;
use Modules\AIAssistant\app\DataTransfer\AIProviderCapabilities;

class DeepSeekProvider extends AbstractOpenAICompatibleProvider
{
    public function getName(): string
    {
        return 'deepseek';
    }

    protected function defaultBaseUrl(): string
    {
        return 'https://api.deepseek.com';
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
