<?php

namespace Tests\Feature\AIAssistant;

use App\Models\Seller;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Modules\AIAssistant\app\Models\AIAgent;
use Modules\AIAssistant\app\Models\AIConversation;
use Modules\AIAssistant\app\Services\HandoffService;
use Tests\TestCase;

/**
 * Covers architecture doc Part III §8/§9/§37: server-side keyword
 * detection (not an LLM confidence score), and the take-over / return-to-AI
 * state transitions.
 */
class HumanHandoffTest extends TestCase
{
    use DatabaseTransactions;

    private function conversation(Seller $seller): AIConversation
    {
        $agent = AIAgent::create(['seller_id' => $seller->id, 'status' => true]);

        return AIConversation::create([
            'seller_id' => $seller->id,
            'ai_agent_id' => $agent->id,
            'guest_id' => 555,
            'mode' => 'shopping',
            'support_status' => AIConversation::SUPPORT_ACTIVE,
        ]);
    }

    public function test_a_default_handoff_phrase_is_detected(): void
    {
        $seller = Seller::create(['f_name' => 'A', 'l_name' => 'V', 'phone' => '1', 'email' => 'hha@test.com', 'password' => bcrypt('x'), 'status' => 'approved']);
        $conversation = $this->conversation($seller);

        $service = app(HandoffService::class);

        $this->assertTrue($service->shouldRequestHuman($conversation, "I'd like to speak to a human please"));
        $this->assertFalse($service->shouldRequestHuman($conversation, 'Do you have this in blue?'));
    }

    public function test_requesting_a_human_sets_support_status_and_is_idempotent(): void
    {
        $seller = Seller::create(['f_name' => 'B', 'l_name' => 'V', 'phone' => '2', 'email' => 'hhb@test.com', 'password' => bcrypt('x'), 'status' => 'approved']);
        $conversation = $this->conversation($seller);

        app(HandoffService::class)->requestHuman($conversation);
        $conversation->refresh();

        $this->assertSame(AIConversation::SUPPORT_HUMAN_REQUESTED, $conversation->support_status);
        $this->assertNotNull($conversation->human_requested_at);
        $firstRequestedAt = $conversation->human_requested_at;

        // Calling it again must not reset the timestamp or change state —
        // a second phrase match / repeated request-human click is a no-op.
        app(HandoffService::class)->requestHuman($conversation->fresh());
        $conversation->refresh();

        $this->assertSame(AIConversation::SUPPORT_HUMAN_REQUESTED, $conversation->support_status);
        $this->assertEquals($firstRequestedAt, $conversation->human_requested_at);
    }

    public function test_take_over_and_return_to_ai_transition_correctly(): void
    {
        $seller = Seller::create(['f_name' => 'C', 'l_name' => 'V', 'phone' => '3', 'email' => 'hhc@test.com', 'password' => bcrypt('x'), 'status' => 'approved']);
        $conversation = $this->conversation($seller);
        $service = app(HandoffService::class);

        $service->takeOver($conversation, $seller->id);
        $conversation->refresh();
        $this->assertTrue($conversation->isHumanActive());
        $this->assertSame($seller->id, $conversation->human_agent_seller_id);
        $this->assertNotNull($conversation->human_taken_over_at);

        $service->returnToAi($conversation, $seller->id);
        $conversation->refresh();
        $this->assertSame(AIConversation::SUPPORT_ACTIVE, $conversation->support_status);
        $this->assertFalse($conversation->isHumanActive());
        $this->assertNotNull($conversation->human_returned_at);
    }

    public function test_a_seller_cannot_take_over_another_sellers_conversation_via_the_inbox_controller(): void
    {
        $sellerA = Seller::create(['f_name' => 'D', 'l_name' => 'V', 'phone' => '4', 'email' => 'hhd@test.com', 'password' => bcrypt('x'), 'status' => 'approved']);
        $sellerB = Seller::create(['f_name' => 'E', 'l_name' => 'V', 'phone' => '5', 'email' => 'hhe@test.com', 'password' => bcrypt('x'), 'status' => 'approved']);
        $conversationB = $this->conversation($sellerB);

        $this->actingAs($sellerA, 'seller');

        // withoutExceptionHandling() + expecting the underlying exception,
        // rather than asserting on the rendered 404 response: this app's
        // own error-page rendering crashes on an unrelated, pre-existing
        // undefined constant (DOMAIN_POINTED_DIRECTORY) outside a real
        // served HTTP request — the same issue observed via `php artisan
        // route:list` during the architecture assessment. What actually
        // matters here — that InboxController::ownedConversation() refuses
        // to resolve seller B's conversation for seller A — is proven by
        // the exception being thrown at all.
        $this->withoutExceptionHandling();
        $threw = false;
        try {
            $this->post(route('vendor.ai-assistant.inbox.take-over', $conversationB->id));
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $exception) {
            $threw = true;
        }

        $this->assertTrue($threw, 'Expected a ModelNotFoundException when seller A tries to take over seller B\'s conversation.');
        $this->assertSame(AIConversation::SUPPORT_ACTIVE, $conversationB->fresh()->support_status, "Seller A's take-over attempt must not affect seller B's conversation.");
    }
}
