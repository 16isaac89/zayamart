<?php

namespace Modules\AIAssistant\app\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Modules\AIAssistant\app\DataTransfer\AIUsage;
use Modules\AIAssistant\app\Models\AIProviderModel;
use Modules\AIAssistant\app\Models\AiUsageRecord;
use Modules\AIAssistant\app\Services\AIUsageCostCalculator;

/**
 * Not latency-sensitive — deferred off the live chat-turn request, unlike
 * the chat turn itself (see architecture doc Part II §13). Runs on the
 * 'aiassistant.queue_connection' connection, which defaults to whatever the
 * app's own default queue connection is (sync, out of the box) so nothing
 * changes in production until an operator points it at a real worker.
 *
 * billingMode/aiProviderId/vendorAiProviderId come straight off the
 * ResolvedProvider that served the turn, so a vendor-owned turn is recorded
 * with vendor_ai_provider_id set and ai_provider_id null — never billed to
 * the platform (brief §28/§31).
 */
class RecordAIUsageJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        private readonly int $sellerId,
        private readonly int $conversationId,
        private readonly string $billingMode,
        private readonly ?int $aiProviderId,
        private readonly ?int $aiProviderModelId,
        private readonly ?int $vendorAiProviderId,
        private readonly int $inputTokens,
        private readonly int $outputTokens,
        private readonly int $cachedTokens,
        private readonly bool $estimated,
    ) {
    }

    public function handle(AIUsageCostCalculator $calculator): void
    {
        $model = $this->aiProviderModelId ? AIProviderModel::find($this->aiProviderModelId) : null;
        $usage = new AIUsage($this->inputTokens, $this->outputTokens, $this->cachedTokens, $this->estimated);

        // No cost estimate when there's no matching pricing row (e.g. a
        // vendor-owned model that isn't in the curated catalog) — the
        // vendor is billed directly by their own provider in that case,
        // not by us, so a fabricated estimate would be actively misleading.
        $cost = $model ? $calculator->estimateCost($model, $usage) : 0.0;

        AiUsageRecord::create([
            'seller_id' => $this->sellerId,
            'billing_mode' => $this->billingMode,
            'ai_conversation_id' => $this->conversationId,
            'ai_provider_id' => $this->aiProviderId,
            'ai_provider_model_id' => $this->aiProviderModelId,
            'vendor_ai_provider_id' => $this->vendorAiProviderId,
            'input_tokens' => $this->inputTokens,
            'output_tokens' => $this->outputTokens,
            'cached_tokens' => $this->cachedTokens,
            'estimated_cost' => $cost,
            'currency' => $model?->currency ?? 'USD',
            'usage_estimated' => $this->estimated || !$model,
        ]);
    }
}
