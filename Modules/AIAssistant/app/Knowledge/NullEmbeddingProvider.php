<?php

namespace Modules\AIAssistant\app\Knowledge;

use Modules\AIAssistant\app\Contracts\KnowledgeEmbeddingProviderInterface;

/**
 * The only implementation wired up in this release — see
 * KnowledgeEmbeddingProviderInterface's docblock. Kept as an explicit,
 * honest no-op rather than silently skipping the interface entirely.
 */
class NullEmbeddingProvider implements KnowledgeEmbeddingProviderInterface
{
    public function embed(string $text): array
    {
        return [];
    }
}
