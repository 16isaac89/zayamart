<?php

namespace Modules\AIAssistant\app\Services;

use Illuminate\Support\Facades\Storage;
use Modules\AIAssistant\app\Contracts\KnowledgeEmbeddingProviderInterface;
use Modules\AIAssistant\app\Contracts\TextExtractorInterface;
use Modules\AIAssistant\app\Knowledge\TextChunker;
use Modules\AIAssistant\app\Models\AIKnowledgeChunk;
use Modules\AIAssistant\app\Models\AIKnowledgeDocument;

/**
 * Document -> storage -> text extraction -> cleaning -> chunking ->
 * embedding -> chunk storage (brief §15). Runs inside ProcessKnowledgeDocumentJob
 * (queued) — never in the request that uploaded the file.
 */
class KnowledgeIngestionService
{
    /**
     * @param TextExtractorInterface[] $extractors
     */
    public function __construct(
        private readonly array $extractors,
        private readonly TextChunker $chunker,
        private readonly KnowledgeEmbeddingProviderInterface $embeddingProvider,
    ) {
    }

    public function ingest(AIKnowledgeDocument $document): void
    {
        $extractor = $this->extractorFor($document->extension);
        if (!$extractor) {
            $document->update(['status' => AIKnowledgeDocument::STATUS_FAILED, 'failure_reason' => "Unsupported file type: .{$document->extension}"]);
            return;
        }

        $disk = $document->storage_type ?: 'public';
        $relativePath = 'ai-knowledge/' . $document->disk_path;
        $absolutePath = Storage::disk($disk)->path($relativePath);

        try {
            $text = $extractor->extract($absolutePath);
        } catch (\RuntimeException $exception) {
            $document->update(['status' => AIKnowledgeDocument::STATUS_FAILED, 'failure_reason' => $exception->getMessage()]);
            return;
        }

        $chunks = $this->chunker->chunk($text);
        if (empty($chunks)) {
            $document->update(['status' => AIKnowledgeDocument::STATUS_FAILED, 'failure_reason' => 'No usable text content found in this document.']);
            return;
        }

        AIKnowledgeChunk::where('ai_knowledge_document_id', $document->id)->delete();

        foreach ($chunks as $index => $chunkText) {
            $embedding = $this->embeddingProvider->embed($chunkText);

            AIKnowledgeChunk::create([
                'ai_knowledge_document_id' => $document->id,
                'seller_id' => $document->seller_id,
                'chunk_index' => $index,
                'content' => $chunkText,
                'embedding' => empty($embedding) ? null : json_encode($embedding),
            ]);
        }

        $document->update([
            'status' => AIKnowledgeDocument::STATUS_INDEXED,
            'chunk_count' => count($chunks),
            'indexed_at' => now(),
            'failure_reason' => null,
        ]);
    }

    private function extractorFor(?string $extension): ?TextExtractorInterface
    {
        foreach ($this->extractors as $extractor) {
            if ($extractor->supports((string)$extension)) {
                return $extractor;
            }
        }

        return null;
    }
}
