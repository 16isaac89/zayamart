<?php

namespace Modules\AIAssistant\app\Knowledge;

use Modules\AIAssistant\app\Contracts\KnowledgeEmbeddingProviderInterface;

/**
 * The implementation currently wired up in AIAssistantServiceProvider — see
 * its binding comment for why (no funded embeddings provider yet; DeepSeek,
 * this platform's vendor chat provider, has no embeddings endpoint anyway).
 * OpenAIEmbeddingProvider exists as the real implementation, ready to swap
 * in. Kept as an explicit, honest no-op rather than silently skipping the
 * interface entirely.
 */
class NullEmbeddingProvider implements KnowledgeEmbeddingProviderInterface
{
    public function embed(string $text): array
    {
        return [];
    }
}
