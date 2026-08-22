<?php

namespace Tests\Unit\AIAssistant;

use Modules\AIAssistant\app\DataTransfer\AIUsage;
use Modules\AIAssistant\app\Models\AIProviderModel;
use Modules\AIAssistant\app\Services\AIUsageCostCalculator;
use PHPUnit\Framework\TestCase;

/**
 * Cost is a lookup + multiply against ai_provider_models — never a
 * hard-coded provider price in application code (architecture doc Part II
 * §7/§10). Pure calculation, no DB needed.
 */
class AIUsageCostCalculatorTest extends TestCase
{
    public function test_computes_cost_from_the_models_own_pricing_row(): void
    {
        $model = new AIProviderModel([
            'input_price' => 3.00,  // per 1,000,000 tokens
            'output_price' => 15.00,
            'currency' => 'USD',
        ]);

        $usage = new AIUsage(inputTokens: 1_000_000, outputTokens: 1_000_000, cachedTokens: 0);

        $cost = (new AIUsageCostCalculator())->estimateCost($model, $usage);

        $this->assertEqualsWithDelta(18.00, $cost, 0.0001);
    }

    public function test_cached_tokens_are_priced_at_the_cached_rate_not_the_full_input_rate(): void
    {
        $model = new AIProviderModel([
            'input_price' => 3.00,
            'cached_input_price' => 0.30,
            'output_price' => 15.00,
        ]);

        $usage = new AIUsage(inputTokens: 1_000_000, outputTokens: 0, cachedTokens: 1_000_000);

        $cost = (new AIUsageCostCalculator())->estimateCost($model, $usage);

        $this->assertEqualsWithDelta(0.30, $cost, 0.0001);
    }

    public function test_falls_back_to_the_input_rate_when_no_cached_rate_is_configured(): void
    {
        $model = new AIProviderModel(['input_price' => 3.00, 'output_price' => 0]);
        $usage = new AIUsage(inputTokens: 1_000_000, outputTokens: 0, cachedTokens: 1_000_000);

        $cost = (new AIUsageCostCalculator())->estimateCost($model, $usage);

        $this->assertEqualsWithDelta(3.00, $cost, 0.0001);
    }

    public function test_changing_a_models_price_row_changes_the_computed_cost_with_no_code_change(): void
    {
        $usage = new AIUsage(inputTokens: 500_000, outputTokens: 0, cachedTokens: 0);
        $calculator = new AIUsageCostCalculator();

        $before = $calculator->estimateCost(new AIProviderModel(['input_price' => 3.00, 'output_price' => 0]), $usage);
        $after = $calculator->estimateCost(new AIProviderModel(['input_price' => 6.00, 'output_price' => 0]), $usage);

        $this->assertEqualsWithDelta(1.50, $before, 0.0001);
        $this->assertEqualsWithDelta(3.00, $after, 0.0001);
    }
}
