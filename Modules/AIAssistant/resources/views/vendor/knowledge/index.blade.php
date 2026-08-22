@extends('layouts.vendor.app')

@section('title', translate('Knowledge_Base'))

@section('content')
    <div class="content container-fluid">
        <div class="d-flex justify-content-between align-items-center gap-3 flex-wrap mb-3 mb-sm-20">
            <h2 class="h1 mb-0 text-capitalize">{{ translate('AI_Knowledge') }}</h2>
        </div>

        <div class="card mb-3 mb-lg-5">
            <div class="card-header"><h5 class="mb-0">{{ translate('Upload_Documents') }}</h5></div>
            <div class="card-body">
                <form method="post" action="{{ route('vendor.ai-assistant.knowledge.store') }}" enctype="multipart/form-data" class="row g-2 align-items-end">
                    @csrf
                    <div class="col-auto">
                        <input type="file" class="form-control" name="document" accept=".pdf,.docx,.txt,.csv" required>
                    </div>
                    <div class="col-auto">
                        <button type="submit" class="btn btn-primary">{{ translate('upload') }}</button>
                    </div>
                    <div class="col-12">
                        <p class="small text-muted mb-0">{{ translate('Supported_PDF_DOCX_TXT_CSV_Max_10MB') }}</p>
                    </div>
                </form>
            </div>
        </div>

        <div class="card">
            <div class="card-header"><h5 class="mb-0">{{ translate('Documents') }}</h5></div>
            <div class="table-responsive">
                <table class="table mb-0">
                    <thead>
                    <tr>
                        <th>{{ translate('Name') }}</th>
                        <th>{{ translate('Type') }}</th>
                        <th>{{ translate('Size') }}</th>
                        <th>{{ translate('Status') }}</th>
                        <th>{{ translate('Chunks') }}</th>
                        <th>{{ translate('Uploaded') }}</th>
                        <th></th>
                    </tr>
                    </thead>
                    <tbody>
                    @forelse($documents as $document)
                        <tr>
                            <td>{{ $document->original_filename }}</td>
                            <td class="text-uppercase">{{ $document->extension }}</td>
                            <td>{{ number_format($document->size_bytes / 1024, 1) }} KB</td>
                            <td>
                                @switch($document->status)
                                    @case('indexed')
                                        <span class="badge bg-success">{{ translate('Indexed') }}</span> @break
                                    @case('failed')
                                        <span class="badge bg-danger" title="{{ $document->failure_reason }}">{{ translate('Failed') }}</span> @break
                                    @default
                                        <span class="badge bg-warning text-dark">{{ translate('Processing') }}</span>
                                @endswitch
                                @if($document->status === 'failed' && $document->failure_reason)
                                    <div class="small text-danger">{{ $document->failure_reason }}</div>
                                @endif
                            </td>
                            <td>{{ $document->chunk_count }}</td>
                            <td>{{ $document->created_at->format('d M Y') }}</td>
                            <td class="text-end">
                                <form method="post" action="{{ route('vendor.ai-assistant.knowledge.reindex', $document->id) }}" class="d-inline">
                                    @csrf
                                    <button type="submit" class="btn btn-sm btn-outline-secondary">{{ translate('Re_index') }}</button>
                                </form>
                                <form method="post" action="{{ route('vendor.ai-assistant.knowledge.destroy', $document->id) }}" class="d-inline">
                                    @csrf @method('DELETE')
                                    <button type="submit" class="btn btn-sm btn-outline-danger" onclick="return confirm('{{ translate('are_you_sure') }}')">{{ translate('delete') }}</button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="text-center text-muted py-4">{{ translate('No_documents_uploaded_yet') }}</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
            <div class="card-footer">{{ $documents->links() }}</div>
        </div>
    </div>
@endsection
