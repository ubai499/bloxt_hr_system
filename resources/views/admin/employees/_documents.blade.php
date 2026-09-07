<div class="row g-3" id="employeeDocuments">
    @forelse ($employee->documents as $document)
        @php
            $status = $document->displayStatus();
            $tone = match ($status) { 'Expired' => 'danger', 'Expiring Soon', 'Review Due' => 'warning', 'Valid' => 'success', default => 'neutral' };
        @endphp
        <div class="col-md-6">
            <div class="panel d-flex gap-3">
                <span class="doc-icon"><i class="bi bi-file-earmark-text"></i></span>
                <div class="flex-grow-1" style="min-width:0;overflow-wrap:anywhere;">
                    <div class="fw-medium">
                        @if ($document->file_path)
                            <a href="{{ route('admin.documents.download', $document) }}" class="text-reset text-decoration-none" aria-label="Download {{ $document->title }}">{{ $document->title }}</a>
                        @else
                            {{ $document->title }}
                        @endif
                    </div>
                    <div class="text-meta mb-2">{{ $document->category }} · Uploaded {{ $document->upload_date?->format('j M Y') }} by {{ $document->uploader?->name ?? $document->uploader_name }}</div>
                    <span class="status-badge badge-{{ $tone }}">{{ $status }}</span>
                    <span class="data-classification-tag classification-{{ str_replace(' ', '-', strtolower($document->access_classification)) }} ms-1">{{ $document->access_classification }}</span>
                    @if ($document->expiry_date)<div class="text-meta mt-2">Expires {{ $document->expiry_date->format('j M Y') }}</div>@endif
                </div>
            </div>
        </div>
    @empty
        <div class="col-12"><div class="empty-state"><div class="empty-state-icon"><i class="bi bi-folder2-open"></i></div><div class="empty-state-title">No documents on file</div><div class="empty-state-text">Upload employment, right-to-work or qualification documents for this employee.</div></div></div>
    @endforelse
</div>
