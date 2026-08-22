<?php

namespace Modules\AIAssistant\app\Http\Controllers\Vendor;

use App\Http\Controllers\Controller;
use App\Traits\FileManagerTrait;
use Devrabiul\ToastMagic\Facades\ToastMagic;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Modules\AIAssistant\app\Jobs\ProcessKnowledgeDocumentJob;
use Modules\AIAssistant\app\Models\AIKnowledgeChunk;
use Modules\AIAssistant\app\Models\AIKnowledgeDocument;
use Modules\AIAssistant\app\Services\AuditLogger;

/**
 * brief §14/§18. "Train the bot" here means uploading business knowledge,
 * not fine-tuning a model — uploads are queued for ingestion
 * (ProcessKnowledgeDocumentJob), never processed inline.
 */
class KnowledgeBaseController extends Controller
{
    use FileManagerTrait;

    public function __construct(private readonly AuditLogger $auditLogger)
    {
    }

    public function index(): View
    {
        $sellerId = auth('seller')->id();
        $documents = AIKnowledgeDocument::where('seller_id', $sellerId)->latest()->paginate(20);

        return view('aiassistant::vendor.knowledge.index', compact('documents'));
    }

    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'document' => ['required', 'file', 'mimes:txt,csv,pdf,docx', 'max:10240'],
        ]);

        $sellerId = auth('seller')->id();
        $file = $request->file('document');
        $extension = strtolower($file->getClientOriginalExtension());

        $filename = $this->fileUpload('ai-knowledge/', $extension, $file);

        $document = AIKnowledgeDocument::create([
            'seller_id' => $sellerId,
            'original_filename' => $file->getClientOriginalName(),
            'disk_path' => $filename,
            'storage_type' => config('filesystems.disks.default') ?? 'public',
            'mime_type' => $file->getMimeType(),
            'extension' => $extension,
            'size_bytes' => $file->getSize(),
            'status' => AIKnowledgeDocument::STATUS_PROCESSING,
        ]);

        ProcessKnowledgeDocumentJob::dispatch($document->id)->onConnection(config('aiassistant.queue_connection'));

        $this->auditLogger->log('seller', $sellerId, $sellerId, 'knowledge_document_uploaded', "Vendor #{$sellerId} uploaded \"{$file->getClientOriginalName()}\".");
        ToastMagic::success(translate('Document_uploaded_processing_will_finish_shortly'));

        return back();
    }

    public function reindex(int $document): RedirectResponse
    {
        $document = $this->ownedDocument($document);

        $document->update(['status' => AIKnowledgeDocument::STATUS_PROCESSING, 'failure_reason' => null]);
        ProcessKnowledgeDocumentJob::dispatch($document->id)->onConnection(config('aiassistant.queue_connection'));

        ToastMagic::success(translate('Re_indexing_started'));

        return back();
    }

    public function destroy(int $document): RedirectResponse
    {
        $document = $this->ownedDocument($document);
        $sellerId = auth('seller')->id();

        if ($document->disk_path) {
            $this->delete('ai-knowledge/' . $document->disk_path);
        }
        $filename = $document->original_filename;

        AIKnowledgeChunk::where('ai_knowledge_document_id', $document->id)->delete();
        $document->delete();

        $this->auditLogger->log('seller', $sellerId, $sellerId, 'knowledge_document_deleted', "Vendor #{$sellerId} deleted \"{$filename}\".");
        ToastMagic::success(translate('Document_deleted'));

        return back();
    }

    private function ownedDocument(int $id): AIKnowledgeDocument
    {
        return AIKnowledgeDocument::where('id', $id)->where('seller_id', auth('seller')->id())->firstOrFail();
    }
}
