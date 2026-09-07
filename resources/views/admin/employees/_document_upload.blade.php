<div class="modal fade" id="uploadDocModal" tabindex="-1" aria-hidden="true" aria-labelledby="profileDocumentTitle">
    <div class="modal-dialog modal-dialog-centered"><div class="modal-content">
        <form id="uploadDocForm" method="POST" action="{{ route('admin.employees.documents.store', $employee) }}" enctype="multipart/form-data">
            @csrf
            <div class="modal-header"><h2 class="modal-title h5" id="profileDocumentTitle">Upload Document</h2><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button></div>
            <div class="modal-body">
                @if ($errors->any())<div class="alert alert-danger" role="alert">Please check the highlighted fields. If you attached a file, select it again.</div>@endif
                <div class="form-field mb-3 {{ $errors->has('title') ? 'field-invalid' : '' }}">
                    <label class="form-label" for="profile_document_title">Document title<span class="required-indicator">*</span></label>
                    <input class="form-control" id="profile_document_title" name="title" value="{{ old('title') }}" maxlength="255" required aria-describedby="profileTitleError">
                    <div class="invalid-feedback-custom" id="profileTitleError">@error('title'){{ $message }}@enderror</div>
                </div>
                <div class="form-grid-2">
                    @foreach (['category' => ['Category', $documentCategories], 'access_classification' => ['Access classification', $documentClassifications]] as $name => [$label, $options])
                        <div class="form-field {{ $errors->has($name) ? 'field-invalid' : '' }}"><label class="form-label" for="profile_document_{{ $name }}">{{ $label }}</label>
                            <select class="form-select" id="profile_document_{{ $name }}" name="{{ $name }}" aria-describedby="profile_{{ $name }}Error">@foreach ($options as $option)<option @selected(old($name) === $option)>{{ $option }}</option>@endforeach</select>
                            <div class="invalid-feedback-custom" id="profile_{{ $name }}Error">@error($name){{ $message }}@enderror</div>
                        </div>
                    @endforeach
                    @foreach (['issue_date' => 'Issue date', 'expiry_date' => 'Expiry date'] as $name => $label)
                        <div class="form-field {{ $errors->has($name) ? 'field-invalid' : '' }}"><label class="form-label" for="profile_document_{{ $name }}">{{ $label }}</label>
                            <input type="date" class="form-control" name="{{ $name }}" id="profile_document_{{ $name }}" value="{{ old($name) }}" aria-describedby="profile_{{ $name }}Error">
                            <div class="invalid-feedback-custom" id="profile_{{ $name }}Error">@error($name){{ $message }}@enderror</div>
                        </div>
                    @endforeach
                </div>
                <div class="mt-3 form-field {{ $errors->has('attachment') ? 'field-invalid' : '' }}"><label class="form-label" for="profile_document_attachment">Document file</label><input type="file" class="form-control" name="attachment" id="profile_document_attachment" accept=".pdf,.doc,.docx" aria-describedby="profileFileHelp profileFileError"><div class="form-text-help" id="profileFileHelp">Optional. PDF, DOC or DOCX, up to 10 MB.</div><div class="invalid-feedback-custom" id="profileFileError">@error('attachment'){{ $message }}@enderror</div></div>
                <div class="mt-3 form-field {{ $errors->has('notes') ? 'field-invalid' : '' }}"><label class="form-label" for="profile_document_notes">Notes</label><textarea class="form-control" name="notes" id="profile_document_notes" rows="2" maxlength="10000" aria-describedby="profileNotesError">{{ old('notes') }}</textarea><div class="invalid-feedback-custom" id="profileNotesError">@error('notes'){{ $message }}@enderror</div></div>
            </div>
            <div class="modal-footer"><button type="button" class="btn btn-light-custom" data-bs-dismiss="modal">Cancel</button><button type="submit" class="btn btn-primary">Save Document</button></div>
        </form>
    </div></div>
</div>
@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', () => {
    const form = document.getElementById('uploadDocForm'), modal = document.getElementById('uploadDocModal');
    let submitting = false;
    form.addEventListener('submit', event => {
        if (submitting) { event.preventDefault(); return; }
        submitting = true; form.querySelector('[type="submit"]').disabled = true;
    });
    modal.addEventListener('shown.bs.modal', () => {
        const field = form.querySelector('.field-invalid input,.field-invalid select,.field-invalid textarea') || form.elements.title;
        field.focus();
    });
    @if ($errors->any()) new bootstrap.Modal(modal).show(); @endif
});
</script>
@endpush
