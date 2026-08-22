<?php

namespace Modules\AIAssistant\app\Services;

use Illuminate\Support\Collection;
use Modules\AIAssistant\app\Models\AIKnowledgeChunk;

/**
 * MariaDB FULLTEXT search over chunks (brief §16) — not vector similarity
 * (see KnowledgeEmbeddingProviderInterface's docblock for why). seller_id
 * is always the caller-supplied, trusted value from ToolExecutionContext —
 * never anything the AI or a request parameter could influence. See
 * architecture doc Part III §17.
 */
class KnowledgeRetrievalService
{
    public function search(int $sellerId, string $query, int $limit = 4): Collection
    {
        $query = trim($query);
        if ($query === '') {
            return collect();
        }

        $results = AIKnowledgeChunk::where('seller_id', $sellerId)
            ->whereRaw('MATCH(content) AGAINST(? IN NATURAL LANGUAGE MODE)', [$query])
            ->limit($limit)
            ->get(['id', 'content']);

        if ($results->isNotEmpty()) {
            return $results;
        }

        // FULLTEXT's natural-language mode can return nothing for very
        // short or stopword-heavy queries — a plain LIKE fallback keeps
        // simple questions ("hours?", "returns?") working.
        return AIKnowledgeChunk::where('seller_id', $sellerId)
            ->where('content', 'like', '%' . $query . '%')
            ->limit($limit)
            ->get(['id', 'content']);
    }
}
