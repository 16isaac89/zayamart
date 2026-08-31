<?php

namespace Modules\AIAssistant\app\Knowledge;

use Illuminate\Support\Facades\Log;
use Modules\AIAssistant\app\Contracts\KnowledgeEmbeddingProviderInterface;
use OpenAI\Contracts\ClientContract;

/**
 * Platform-level OpenAI embeddings (config/openai.php — the same
 * OPENAI_API_KEY already wired for Modules\AI), used regardless of which
 * chat provider a vendor picked for their own agent: DeepSeek and
 * Anthropic have no embeddings endpoint, and retrieval quality isn't a
 * per-vendor choice the way the chat model is. $client is the container's
 * existing openai-php/laravel singleton (OpenAI\Laravel\ServiceProvider) —
 * nothing here builds its own HTTP client or reads the API key directly.
 */
class OpenAIEmbeddingProvider implements KnowledgeEmbeddingProviderInterface
{
    private const MODEL = 'text-embedding-3-small';

    public function __construct(private readonly ClientContract $client)
    {
    }

    public function embed(string $text): array
    {
        // Fails fast instead of making a request guaranteed to 401 — an
        // unconfigured platform key must degrade to keyword search
        // (KnowledgeRetrievalService), not throw. See interface docblock:
        // an empty array is an expected, valid "no embedding" state.
        if (!config('openai.api_key')) {
            return [];
        }

        try {
            $response = $this->client->embeddings()->create([
                'model' => self::MODEL,
                'input' => $text,
            ]);

            return $response->embeddings[0]->embedding ?? [];
        } catch (\Throwable $exception) {
            Log::warning('OpenAIEmbeddingProvider failed', ['error' => $exception->getMessage()]);

            return [];
        }
    }
}
