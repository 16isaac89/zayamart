<?php

namespace Modules\AIAssistant\app\DataTransfer;

use Modules\AIAssistant\app\Contracts\AIProviderInterface;

/**
 * What AIProviderManager::resolveForAgent() hands back — the provider
 * adapter plus everything ConversationService/RecordAIUsageJob need,
 * regardless of which of the three billing modes produced it (platform
 * default, platform managed, or vendor owned). See architecture doc
 * Part III §1.
 */
final class ResolvedProvider
{
    public const BILLING_PLATFORM_DEFAULT = 'platform_default';
    public const BILLING_PLATFORM_MANAGED = 'platform_managed';
    public const BILLING_VENDOR_OWNED = 'vendor_owned';

    public function __construct(
        public readonly AIProviderInterface $provider,
        public readonly string $model,
        public readonly AIProviderCapabilities $capabilities,
        public readonly float $temperature,
        public readonly ?int $maxTokens,
        public readonly string $billingMode,
        /** Platform ai_providers.id — set for platform_default/platform_managed, used for cost/usage attribution to the platform. */
        public readonly ?int $aiProviderId,
        /** ai_provider_models.id, when a matching catalog row exists — used for pricing lookups. */
        public readonly ?int $aiProviderModelId,
        /** vendor_ai_providers.id — set only for vendor_owned, used for usage logging without platform cost attribution. */
        public readonly ?int $vendorAiProviderId,
    ) {
    }
}
