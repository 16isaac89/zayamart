<?php

namespace Tests\Feature\AIAssistant;

use App\Models\Seller;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\Request;
use Modules\AIAssistant\app\DataTransfer\ToolExecutionContext;
use Modules\AIAssistant\app\Models\AIKnowledgeChunk;
use Modules\AIAssistant\app\Models\AIKnowledgeDocument;
use Modules\AIAssistant\app\Services\KnowledgeRetrievalService;
use Modules\AIAssistant\app\Tools\SearchKnowledgeBaseTool;
use Tests\TestCase;

/**
 * Runs against the real, already-installed database (DatabaseTransactions —
 * see SellerIsolationTest's note for why RefreshDatabase is never used
 * here).
 *
 * Covers architecture doc §17: seller_id on ai_knowledge_chunks is
 * denormalized and trusted only from ToolExecutionContext, never from a
 * document_id/chunk_id/seller_id an LLM might echo back — a manipulated
 * argument must never widen retrieval past the authenticated seller.
 */
class KnowledgeIsolationTest extends TestCase
{
    use DatabaseTransactions;

    public function test_search_knowledge_base_never_returns_another_sellers_chunks(): void
    {
        $sellerA = Seller::create(['f_name' => 'A', 'l_name' => 'V', 'phone' => '1', 'email' => 'ka@test.com', 'password' => bcrypt('x'), 'status' => 'approved']);
        $sellerB = Seller::create(['f_name' => 'B', 'l_name' => 'V', 'phone' => '2', 'email' => 'kb@test.com', 'password' => bcrypt('x'), 'status' => 'approved']);

        $docA = AIKnowledgeDocument::create(['seller_id' => $sellerA->id, 'original_filename' => 'a.txt', 'disk_path' => 'a.txt', 'status' => 'indexed']);
        $docB = AIKnowledgeDocument::create(['seller_id' => $sellerB->id, 'original_filename' => 'b.txt', 'disk_path' => 'b.txt', 'status' => 'indexed']);

        AIKnowledgeChunk::create(['ai_knowledge_document_id' => $docA->id, 'seller_id' => $sellerA->id, 'chunk_index' => 0, 'content' => 'Our refund policy allows returns within 7 days.']);
        AIKnowledgeChunk::create(['ai_knowledge_document_id' => $docB->id, 'seller_id' => $sellerB->id, 'chunk_index' => 0, 'content' => 'Our refund policy allows returns within 30 days for seller B.']);

        $context = new ToolExecutionContext($sellerA->id, 1, 1, null, 999, true, Request::create('/'));
        $result = (new SearchKnowledgeBaseTool(app(KnowledgeRetrievalService::class)))->execute(['query' => 'refund policy'], $context);

        $this->assertTrue($result->data['found']);
        $excerpts = implode(' ', $result->data['excerpts']);
        $this->assertStringContainsString('7 days', $excerpts);
        $this->assertStringNotContainsString('30 days', $excerpts, "Seller A's assistant must never surface Seller B's knowledge, even on an identical query.");
    }

    public function test_a_forged_seller_id_style_argument_cannot_widen_knowledge_retrieval(): void
    {
        // search_knowledge_base's parameterSchema doesn't even expose a
        // seller_id/document_id argument (architecture doc §17) — this
        // asserts that passing one anyway has no effect, since the tool
        // only ever reads $context->sellerId.
        $sellerA = Seller::create(['f_name' => 'A', 'l_name' => 'V', 'phone' => '1', 'email' => 'ka2@test.com', 'password' => bcrypt('x'), 'status' => 'approved']);
        $sellerB = Seller::create(['f_name' => 'B', 'l_name' => 'V', 'phone' => '2', 'email' => 'kb2@test.com', 'password' => bcrypt('x'), 'status' => 'approved']);

        $docB = AIKnowledgeDocument::create(['seller_id' => $sellerB->id, 'original_filename' => 'b.txt', 'disk_path' => 'b.txt', 'status' => 'indexed']);
        AIKnowledgeChunk::create(['ai_knowledge_document_id' => $docB->id, 'seller_id' => $sellerB->id, 'chunk_index' => 0, 'content' => 'Confidential seller B pricing strategy document.']);

        $context = new ToolExecutionContext($sellerA->id, 1, 1, null, 999, true, Request::create('/'));
        $result = (new SearchKnowledgeBaseTool(app(KnowledgeRetrievalService::class)))
            ->execute(['query' => 'pricing strategy', 'seller_id' => $sellerB->id, 'document_id' => $docB->id], $context);

        $this->assertFalse($result->data['found']);
    }

    public function test_retrieval_service_scopes_directly_by_seller_id_column_not_via_document_join(): void
    {
        // Defense in depth: even if a document_id were somehow trusted, the
        // chunk row itself carries its own seller_id — a chunk can never be
        // returned for a seller_id that doesn't match its own column.
        $seller = Seller::create(['f_name' => 'C', 'l_name' => 'V', 'phone' => '3', 'email' => 'kc@test.com', 'password' => bcrypt('x'), 'status' => 'approved']);
        $otherSeller = Seller::create(['f_name' => 'D', 'l_name' => 'V', 'phone' => '4', 'email' => 'kd@test.com', 'password' => bcrypt('x'), 'status' => 'approved']);

        $doc = AIKnowledgeDocument::create(['seller_id' => $seller->id, 'original_filename' => 'c.txt', 'disk_path' => 'c.txt', 'status' => 'indexed']);
        AIKnowledgeChunk::create(['ai_knowledge_document_id' => $doc->id, 'seller_id' => $seller->id, 'chunk_index' => 0, 'content' => 'Delivery is available across the whole country.']);

        $results = app(KnowledgeRetrievalService::class)->search($otherSeller->id, 'delivery');

        $this->assertTrue($results->isEmpty());
    }
}
