<?php

namespace Modules\AIAssistant\app\Tools;

use Modules\AIAssistant\app\Contracts\AIToolInterface;
use Modules\AIAssistant\app\DataTransfer\AIToolResult;
use Modules\AIAssistant\app\DataTransfer\ToolExecutionContext;
use Modules\AIAssistant\app\Services\KnowledgeRetrievalService;

/**
 * Retrieves only relevant chunks, never the whole knowledge base (brief
 * §16). seller_id comes from $context, not from any tool argument — a
 * seller_id/document_id in $arguments would simply be ignored even if an
 * LLM were somehow induced to send one. See architecture doc Part III §17.
 */
class SearchKnowledgeBaseTool implements AIToolInterface
{
    public function __construct(private readonly KnowledgeRetrievalService $retrieval)
    {
    }

    public function name(): string
    {
        return 'search_knowledge_base';
    }

    public function description(): string
    {
        return "Search this vendor's uploaded business documents (policies, FAQs, company info) for an answer. Use this for questions about business knowledge that isn't a product — e.g. return policy, opening hours, delivery areas.";
    }

    public function parameterSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'query' => ['type' => 'string', 'description' => "The customer's question, in their own words"],
            ],
            'required' => ['query'],
        ];
    }

    public function execute(array $arguments, ToolExecutionContext $context): AIToolResult
    {
        $query = trim((string)($arguments['query'] ?? ''));
        if ($query === '') {
            return AIToolResult::fail('query is required.');
        }

        $chunks = $this->retrieval->search($context->sellerId, $query);

        if ($chunks->isEmpty()) {
            return AIToolResult::ok(['found' => false, 'excerpts' => []]);
        }

        return AIToolResult::ok([
            'found' => true,
            'excerpts' => $chunks->pluck('content')->values()->toArray(),
        ]);
    }
}
