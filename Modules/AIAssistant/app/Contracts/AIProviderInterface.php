<?php

namespace Modules\AIAssistant\app\Contracts;

use Modules\AIAssistant\app\DataTransfer\AIChatRequest;
use Modules\AIAssistant\app\DataTransfer\AIProviderCapabilities;
use Modules\AIAssistant\app\DataTransfer\AIProviderCredentials;
use Modules\AIAssistant\app\DataTransfer\AIResponse;
use Modules\AIAssistant\app\Exceptions\AIProviderException;

/**
 * Every concrete AI vendor adapter (DeepSeek, OpenAI, Anthropic, Gemini,
 * future providers) implements this and only this. Application code never
 * depends on a concrete provider — see AIProviderManager and the
 * architecture doc, Part II §1/§2.
 */
interface AIProviderInterface
{
    /**
     * Stable identifier, matching ai_providers.key ('deepseek', 'openai', ...).
     */
    public function getName(): string;

    public function getCapabilities(): AIProviderCapabilities;

    public function setCredentials(AIProviderCredentials $credentials): void;

    /**
     * Send one chat turn (system instructions + message history + tool
     * definitions) and return a normalized AIResponse. Implementations map
     * their own SDK/HTTP response shape into AIResponse/AIToolCall/AIUsage
     * — that mapping never leaks outside the provider class.
     *
     * @throws AIProviderException on transport/auth/provider-side failure.
     */
    public function chat(AIChatRequest $request): AIResponse;
}
