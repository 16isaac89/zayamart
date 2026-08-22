<?php

namespace Modules\AIAssistant\app\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Modules\AIAssistant\app\Models\AIKnowledgeDocument;
use Modules\AIAssistant\app\Services\KnowledgeIngestionService;

// Queueable (not just InteractsWithQueue) is required for the
// dispatch(...)->onConnection(...) chain used by this job's caller — its
// absence produced a real, caught-by-testing bug: "Call to undefined
// method ...::onConnection()". Fixed here and in RecordAIUsageJob, which
// had the same gap.

/**
 * Large document processing must never block the request that uploaded the
 * file (brief §39/§40). Idempotent by construction — KnowledgeIngestionService
 * deletes any existing chunks for the document before re-inserting, so a
 * retried/redelivered job produces the same end state, not duplicates.
 */
class ProcessKnowledgeDocumentJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $timeout = 300;

    public function __construct(private readonly int $documentId)
    {
    }

    public function handle(KnowledgeIngestionService $ingestionService): void
    {
        $document = AIKnowledgeDocument::find($this->documentId);
        if (!$document) {
            return;
        }

        $ingestionService->ingest($document);
    }

    public function failed(\Throwable $exception): void
    {
        AIKnowledgeDocument::where('id', $this->documentId)->update([
            'status' => AIKnowledgeDocument::STATUS_FAILED,
            'failure_reason' => 'Processing failed after multiple attempts.',
        ]);
    }
}
