<?php

namespace Modules\AIAssistant\app\Services;

use Modules\AIAssistant\app\Contracts\AIProviderInterface;
use Modules\AIAssistant\app\DataTransfer\AIChatRequest;
use Modules\AIAssistant\app\DataTransfer\AIProviderCapabilities;
use Modules\AIAssistant\app\DataTransfer\AIProviderCredentials;
use Modules\AIAssistant\app\DataTransfer\ResolvedProvider;
use Modules\AIAssistant\app\Exceptions\AIProviderException;
use Modules\AIAssistant\app\Models\AIAgent;
use Modules\AIAssistant\app\Models\AIProviderConfig;
use Modules\AIAssistant\app\Models\AIProviderModel;

/**
 * The one class the rest of the application depends on. ConversationService
 * calls resolveProvider()/createConversationRequest() here — it never
 * instantiates DeepSeekProvider, OpenAIProvider, etc. directly. See
 * architecture doc Part II §1/§5 ("Never: ConversationService → DeepSeekService").
 */
class AIProviderManager
{
    /** @var AIProviderInterface[] keyed by getName() */
    private array $providers = [];

    /**
     * @param AIProviderInterface[] $providers
     */
    public function __construct(array $providers)
    {
        foreach ($providers as $provider) {
            $this->providers[$provider->getName()] = $provider;
        }
    }

    /**
     * @throws AIProviderException
     */
    public function resolveProvider(AIProviderConfig $config): AIProviderInterface
    {
        $providerKey = $config->provider?->key;
        $provider = $this->providers[$providerKey] ?? null;

        if (!$provider) {
            throw new AIProviderException("No provider adapter registered for '{$providerKey}'.");
        }

        if (!$config->provider->isConnected()) {
            throw new AIProviderException("Provider '{$providerKey}' is not connected.");
        }

        $provider->setCredentials(new AIProviderCredentials(
            apiKey: $config->provider->api_key,
            baseUrl: $config->provider->base_url,
        ));

        return $provider;
    }

    /**
     * @throws AIProviderException
     */
    public function resolveAdapterByKey(string $providerKey): AIProviderInterface
    {
        return $this->providers[$providerKey]
            ?? throw new AIProviderException("No provider adapter registered for '{$providerKey}'.");
    }

    public function resolveModel(AIProviderConfig $config): string
    {
        return $config->model?->model_name
            ?? throw new AIProviderException('AI provider config has no model attached.');
    }

    /**
     * A specific model can be advertised with narrower capabilities than
     * its provider generally supports (e.g. a cheaper model without tool
     * calling) via ai_provider_models.capabilities — that override wins
     * when present. See architecture doc Part II §2.
     */
    public function getCapabilities(AIProviderConfig $config): AIProviderCapabilities
    {
        if (!empty($config->model?->capabilities)) {
            return AIProviderCapabilities::fromArray($config->model->capabilities);
        }

        return $this->resolveProvider($config)->getCapabilities();
    }

    /**
     * @param \Modules\AIAssistant\app\DataTransfer\ChatMessage[] $messages
     * @param \Modules\AIAssistant\app\DataTransfer\AIToolDefinition[] $tools
     */
    public function createConversationRequest(
        AIProviderConfig $config,
        ?string $systemInstructions,
        array $messages,
        array $tools = [],
    ): AIChatRequest {
        $capabilities = $this->getCapabilities($config);

        return new AIChatRequest(
            model: $this->resolveModel($config),
            systemInstructions: $systemInstructions,
            messages: $messages,
            // Tools are only sent to providers that can actually call them —
            // sending a tools array to a non-tool-calling provider would
            // just be ignored or rejected depending on the provider, so
            // ConversationService's degraded (pre-fetch) path relies on this
            // being empty here rather than guessing at provider behavior.
            tools: $capabilities->toolCalling ? $tools : [],
            temperature: (float)$config->temperature,
            maxTokens: $config->max_tokens,
        );
    }

    /**
     * The full three-way billing resolution (brief §2/§30): platform
     * default, a specific platform-managed provider, or the vendor's own
     * credentials. This is the entrypoint ConversationService now calls —
     * resolveProvider()/createConversationRequest() above stay exactly as
     * they were (still directly tested against AIProviderConfig) since
     * platform_default/platform_managed just delegate to them underneath.
     *
     * @throws AIProviderException
     */
    public function resolveForAgent(AIAgent $agent): ResolvedProvider
    {
        return match ($agent->billing_mode) {
            AIAgent::BILLING_VENDOR_OWNED => $this->resolveVendorOwned($agent),
            default => $this->resolvePlatformBilled($agent), // platform_default and platform_managed both read ai_provider_config_id
        };
    }

    public function createRequestFor(ResolvedProvider $resolved, ?string $systemInstructions, array $messages, array $tools = []): AIChatRequest
    {
        return new AIChatRequest(
            model: $resolved->model,
            systemInstructions: $systemInstructions,
            messages: $messages,
            tools: $resolved->capabilities->toolCalling ? $tools : [],
            temperature: $resolved->temperature,
            maxTokens: $resolved->maxTokens,
        );
    }

    private function resolvePlatformBilled(AIAgent $agent): ResolvedProvider
    {
        $config = $agent->resolvedProviderConfig();
        if (!$config) {
            throw new AIProviderException('No platform AI provider config available.');
        }

        // platform_managed must be pinned to a provider the platform has
        // explicitly opted into vendor use — re-checked here, not just at
        // save time, in case an admin revokes availability after a vendor
        // already selected it (brief §29/§30).
        if ($agent->billing_mode === AIAgent::BILLING_PLATFORM_MANAGED && !($config->provider?->vendor_managed_available)) {
            throw new AIProviderException('This platform-managed provider is no longer available to vendors.');
        }

        $provider = $this->resolveProvider($config);

        return new ResolvedProvider(
            provider: $provider,
            model: $this->resolveModel($config),
            capabilities: $this->getCapabilities($config),
            temperature: (float)$config->temperature,
            maxTokens: $config->max_tokens,
            billingMode: $agent->billing_mode,
            aiProviderId: $config->ai_provider_id,
            aiProviderModelId: $config->ai_provider_model_id,
            vendorAiProviderId: null,
        );
    }

    private function resolveVendorOwned(AIAgent $agent): ResolvedProvider
    {
        $vendorProvider = $agent->vendorProvider;

        if (!$vendorProvider || (int)$vendorProvider->seller_id !== (int)$agent->seller_id) {
            // The second check is defense in depth, not a real-world path —
            // vendor_ai_provider_id is only ever set by this same vendor's
            // own settings controller — but never trust it implicitly.
            throw new AIProviderException('No vendor-owned AI provider configured.');
        }

        if (!$vendorProvider->isConnected()) {
            throw new AIProviderException('Your AI provider is not connected.');
        }

        if (!($vendorProvider->provider?->vendor_owned_allowed)) {
            throw new AIProviderException('Bringing your own key for this provider is no longer allowed.');
        }

        $providerKey = $vendorProvider->provider->key;
        $adapter = $this->providers[$providerKey] ?? null;
        if (!$adapter) {
            throw new AIProviderException("No provider adapter registered for '{$providerKey}'.");
        }

        $adapter->setCredentials(new AIProviderCredentials(
            apiKey: $vendorProvider->api_key,
            baseUrl: $vendorProvider->base_url,
        ));

        $model = $agent->vendor_model_name
            ?? throw new AIProviderException('No model selected for your AI provider.');

        // Best-effort capability/pricing lookup: if this exact model name
        // happens to match a curated ai_provider_models row under the same
        // provider, use its capability profile; otherwise fall back to the
        // provider adapter's own generic capabilities. Vendor-owned usage
        // still gets logged (RecordAIUsageJob), just without a platform
        // cost estimate when no matching catalog row exists — see
        // AIUsageCostCalculator.
        $catalogModel = AIProviderModel::where('ai_provider_id', $vendorProvider->ai_provider_id)
            ->where('model_name', $model)
            ->first();

        return new ResolvedProvider(
            provider: $adapter,
            model: $model,
            capabilities: $catalogModel && !empty($catalogModel->capabilities)
                ? AIProviderCapabilities::fromArray($catalogModel->capabilities)
                : $adapter->getCapabilities(),
            temperature: 0.3,
            maxTokens: null,
            billingMode: AIAgent::BILLING_VENDOR_OWNED,
            aiProviderId: null,
            aiProviderModelId: $catalogModel?->id,
            vendorAiProviderId: $vendorProvider->id,
        );
    }
}
