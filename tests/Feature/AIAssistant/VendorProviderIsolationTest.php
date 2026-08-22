<?php

namespace Tests\Feature\AIAssistant;

use App\Models\Seller;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Modules\AIAssistant\app\Exceptions\AIProviderException;
use Modules\AIAssistant\app\Models\AIAgent;
use Modules\AIAssistant\app\Models\AIProvider;
use Modules\AIAssistant\app\Models\VendorAIProvider;
use Modules\AIAssistant\app\Services\AIProviderManager;
use Tests\TestCase;

/**
 * Covers architecture doc Part III §1/§2/§30: a vendor's own AI provider
 * credentials must never be usable by another vendor's agent, and API keys
 * must round-trip through encryption transparently (never stored/compared
 * as plaintext).
 */
class VendorProviderIsolationTest extends TestCase
{
    use DatabaseTransactions;

    public function test_a_vendor_owned_provider_belonging_to_another_seller_is_rejected(): void
    {
        $sellerA = Seller::create(['f_name' => 'A', 'l_name' => 'V', 'phone' => '1', 'email' => 'vpa@test.com', 'password' => bcrypt('x'), 'status' => 'approved']);
        $sellerB = Seller::create(['f_name' => 'B', 'l_name' => 'V', 'phone' => '2', 'email' => 'vpb@test.com', 'password' => bcrypt('x'), 'status' => 'approved']);

        $catalogProvider = AIProvider::firstOrCreate(
            ['key' => 'deepseek'],
            ['display_name' => 'DeepSeek', 'status' => 'disabled', 'vendor_owned_allowed' => true],
        );

        // Seller B's own credentials.
        $sellerBProvider = VendorAIProvider::create([
            'seller_id' => $sellerB->id,
            'ai_provider_id' => $catalogProvider->id,
            'api_key' => 'sk-seller-b-secret-key',
            'status' => 'connected',
        ]);

        // Seller A's agent is misconfigured (or tampered with) to point at
        // Seller B's vendor_ai_provider row.
        $agentA = AIAgent::create([
            'seller_id' => $sellerA->id,
            'billing_mode' => AIAgent::BILLING_VENDOR_OWNED,
            'vendor_ai_provider_id' => $sellerBProvider->id,
            'vendor_model_name' => 'deepseek-chat',
        ]);

        $this->expectException(AIProviderException::class);
        $this->expectExceptionMessage('No vendor-owned AI provider configured.');

        app(AIProviderManager::class)->resolveForAgent($agentA);
    }

    public function test_a_disconnected_vendor_owned_provider_is_rejected_even_if_it_belongs_to_the_right_seller(): void
    {
        $seller = Seller::create(['f_name' => 'C', 'l_name' => 'V', 'phone' => '3', 'email' => 'vpc@test.com', 'password' => bcrypt('x'), 'status' => 'approved']);
        $catalogProvider = AIProvider::firstOrCreate(
            ['key' => 'openai'],
            ['display_name' => 'OpenAI', 'status' => 'disabled', 'vendor_owned_allowed' => true],
        );

        $vendorProvider = VendorAIProvider::create([
            'seller_id' => $seller->id,
            'ai_provider_id' => $catalogProvider->id,
            'api_key' => 'sk-not-tested-yet',
            'status' => 'disabled', // never passed a connection test
        ]);

        $agent = AIAgent::create([
            'seller_id' => $seller->id,
            'billing_mode' => AIAgent::BILLING_VENDOR_OWNED,
            'vendor_ai_provider_id' => $vendorProvider->id,
            'vendor_model_name' => 'gpt-4o-mini',
        ]);

        $this->expectException(AIProviderException::class);
        $this->expectExceptionMessage('Your AI provider is not connected.');

        app(AIProviderManager::class)->resolveForAgent($agent);
    }

    public function test_api_key_round_trips_through_encryption_and_is_never_stored_in_plaintext(): void
    {
        $seller = Seller::create(['f_name' => 'D', 'l_name' => 'V', 'phone' => '4', 'email' => 'vpd@test.com', 'password' => bcrypt('x'), 'status' => 'approved']);
        $catalogProvider = AIProvider::firstOrCreate(
            ['key' => 'anthropic'],
            ['display_name' => 'Anthropic (Claude)', 'status' => 'disabled', 'vendor_owned_allowed' => true],
        );

        $plaintextKey = 'sk-ant-real-secret-value-12345';
        $vendorProvider = VendorAIProvider::create([
            'seller_id' => $seller->id,
            'ai_provider_id' => $catalogProvider->id,
            'api_key' => $plaintextKey,
            'status' => 'connected',
        ]);

        // The Eloquent 'encrypted' cast decrypts transparently on read...
        $this->assertSame($plaintextKey, $vendorProvider->fresh()->api_key);

        // ...but the raw database column must never contain the plaintext.
        $rawValue = \Illuminate\Support\Facades\DB::table('vendor_ai_providers')->where('id', $vendorProvider->id)->value('api_key');
        $this->assertStringNotContainsString($plaintextKey, $rawValue);

        // And it must never appear in the model's array/JSON representation
        // (brief §6: "never return decrypted keys to frontend").
        $this->assertArrayNotHasKey('api_key', $vendorProvider->fresh()->toArray());
    }
}
