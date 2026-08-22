<?php

namespace Modules\AIAssistant\app\Services;

use Modules\AIAssistant\app\DataTransfer\AIUsage;
use Modules\AIAssistant\app\Models\AIProviderModel;

/**
 * Cost is a lookup against ai_provider_models + a multiply — never a
 * provider-specific constant in application code (architecture doc Part II
 * §7/§10). Pricing here follows every major provider's own convention:
 * input_price/output_price/cached_input_price are cost per 1,000,000 tokens.
 */
class AIUsageCostCalculator
{
    public function estimateCost(AIProviderModel $model, AIUsage $usage): float
    {
        $uncachedInput = max(0, $usage->inputTokens - $usage->cachedTokens);
        $cachedPrice = $model->cached_input_price ?? $model->input_price;

        $cost = ($uncachedInput / 1_000_000) * $model->input_price
            + ($usage->cachedTokens / 1_000_000) * $cachedPrice
            + ($usage->outputTokens / 1_000_000) * $model->output_price;

        return round($cost, 12);
    }
}
