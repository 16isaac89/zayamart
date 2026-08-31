<?php

namespace Modules\AIAssistant\app\Console\Commands;

use Illuminate\Console\Command;
use Modules\AIAssistant\app\Contracts\KnowledgeEmbeddingProviderInterface;
use Modules\AIAssistant\app\Models\AIKnowledgeChunk;

/**
 * One-off backfill for chunks stored before a real
 * KnowledgeEmbeddingProviderInterface was wired in — they have
 * embedding = null, from when NullEmbeddingProvider was bound. New uploads
 * don't need this: KnowledgeIngestionService already embeds at ingest time.
 * Re-embeds from the already-stored chunk content, so it never re-touches
 * the source document/file.
 */
class BackfillKnowledgeEmbeddingsCommand extends Command
{
    protected $signature = 'aiassistant:backfill-embeddings {--seller_id=}';

    protected $description = 'Embed any AI knowledge base chunks that predate the configured embedding provider.';

    public function handle(KnowledgeEmbeddingProviderInterface $embeddingProvider): int
    {
        $query = AIKnowledgeChunk::whereNull('embedding');
        if ($sellerId = $this->option('seller_id')) {
            $query->where('seller_id', (int)$sellerId);
        }

        $total = 0;
        $embedded = 0;

        $query->orderBy('id')->chunkById(100, function ($chunks) use ($embeddingProvider, &$total, &$embedded) {
            foreach ($chunks as $chunk) {
                $total++;
                $vector = $embeddingProvider->embed($chunk->content);
                if (!empty($vector)) {
                    $chunk->update(['embedding' => json_encode($vector)]);
                    $embedded++;
                }
            }
        });

        $this->info("Embedded {$embedded}/{$total} chunk(s).");
        if ($total > $embedded) {
            $this->warn('Some chunks could not be embedded — check that OPENAI_API_KEY is configured and the queue log for errors.');
        }

        return self::SUCCESS;
    }
}
