<?php

namespace Modules\AIAssistant\app\Services;

use Illuminate\Support\Collection;
use Modules\AIAssistant\app\Contracts\KnowledgeEmbeddingProviderInterface;
use Modules\AIAssistant\app\Models\AIKnowledgeChunk;

/**
 * Semantic (embedding cosine-similarity) search when the configured
 * KnowledgeEmbeddingProviderInterface can actually produce a vector for
 * this query and at least one of this seller's chunks has been embedded;
 * MariaDB FULLTEXT/LIKE search otherwise. An empty embedding is an
 * expected, valid "not available" state (see the interface docblock) —
 * NullEmbeddingProvider, an unconfigured platform key, or a chunk ingested
 * before a real provider was wired in all degrade to keyword search rather
 * than failing. seller_id is always the caller-supplied, trusted value
 * from ToolExecutionContext — never anything the AI or a request parameter
 * could influence. See architecture doc Part III §17.
 *
 * Similarity is computed in PHP over every embedded chunk for the seller —
 * fine for the FAQ/policy-sized knowledge bases this module targets, but
 * not a design that scales to a seller with a huge document set; that
 * would need a real vector index instead.
 */
class KnowledgeRetrievalService
{
    public function __construct(private readonly KnowledgeEmbeddingProviderInterface $embeddingProvider)
    {
    }

    public function search(int $sellerId, string $query, int $limit = 4): Collection
    {
        $query = trim($query);
        if ($query === '') {
            return collect();
        }

        $semantic = $this->semanticSearch($sellerId, $query, $limit);
        if ($semantic !== null && $semantic->isNotEmpty()) {
            return $semantic;
        }

        return $this->keywordSearch($sellerId, $query, $limit);
    }

    private function semanticSearch(int $sellerId, string $query, int $limit): ?Collection
    {
        $queryVector = $this->embeddingProvider->embed($query);
        if (empty($queryVector)) {
            return null;
        }

        $chunks = AIKnowledgeChunk::where('seller_id', $sellerId)
            ->whereNotNull('embedding')
            ->get(['id', 'content', 'embedding']);

        if ($chunks->isEmpty()) {
            return null;
        }

        $threshold = (float)config('aiassistant.knowledge_similarity_threshold', 0.3);

        return $chunks
            ->map(function (AIKnowledgeChunk $chunk) use ($queryVector) {
                $vector = json_decode((string)$chunk->embedding, true);

                return [
                    'id' => $chunk->id,
                    'content' => $chunk->content,
                    'similarity' => is_array($vector) ? $this->cosineSimilarity($queryVector, $vector) : -1.0,
                ];
            })
            ->filter(fn (array $row) => $row['similarity'] >= $threshold)
            ->sortByDesc('similarity')
            ->take($limit)
            ->values();
    }

    private function keywordSearch(int $sellerId, string $query, int $limit): Collection
    {
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

    /**
     * Vectors of mismatched length (e.g. a chunk embedded under a
     * different model before one was standardized on) are treated as "no
     * match" rather than compared term-by-term against the wrong basis.
     */
    private function cosineSimilarity(array $a, array $b): float
    {
        if (count($a) === 0 || count($a) !== count($b)) {
            return -1.0;
        }

        $dot = 0.0;
        $normA = 0.0;
        $normB = 0.0;
        foreach ($a as $i => $value) {
            $dot += $value * $b[$i];
            $normA += $value ** 2;
            $normB += $b[$i] ** 2;
        }

        if ($normA <= 0.0 || $normB <= 0.0) {
            return -1.0;
        }

        return $dot / (sqrt($normA) * sqrt($normB));
    }
}
