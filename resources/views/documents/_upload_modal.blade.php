  <div class="modal fade" id="docModal" tabindex="-1" aria-hidden="true" aria-labelledby="docModalTitle">
    <div class="modal-dialog modal-dialog-centered">
      <div class="modal-content">
        <form id="docForm" method="POST" action="{{ route($routePrefix.'.store') }}" enctype="multipart/form-data">
          @csrf
          <div class="modal-header"><h2 class="modal-title h5" id="docModalTitle">Upload Document</h2><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button></div>
          <div class="modal-body">
            <div id="docFormError" class="alert alert-danger d-none" role="alert"></div>
            <div class="form-grid-2">
              <div class="form-field"><label class="form-label" for="doc_title">Document title<span class="required-indicator">*</span></label><input class="form-control" name="title" required id="doc_title" aria-describedby="doc_titleError" maxlength="255"><div class="invalid-feedback-custom" id="doc_titleError" data-error-for="title"></div></div>
              <div class="form-field"><label class="form-label" for="docEmployeeSelect">Employee</label><select class="form-select" name="employee_id" id="docEmployeeSelect" aria-describedby="docEmployeeSelectError">@unless($selfService)<option value="">Company-wide (not employee-specific)</option>@endunless
              @foreach ($employees as $employee)<option value="{{ $employee->id }}">{{ $employee->name }}</option>@endforeach</select><div class="invalid-feedback-custom" id="docEmployeeSelectError" data-error-for="employee_id"></div></div>
              <div class="form-field"><label class="form-label" for="docCategorySelect">Category</label><select class="form-select" name="category" id="docCategorySelect" aria-describedby="docCategorySelectError">@foreach ($categories as $category)<option @selected($category === ($defaultCategory === 'all' ? 'Identity' : $defaultCategory))>{{ $category }}</option>@endforeach</select><div class="invalid-feedback-custom" id="docCategorySelectError" data-error-for="category"></div></div>
              <div class="form-field"><label class="form-label" for="docClassSelect">Access classification</label><select class="form-select" name="access_classification" id="docClassSelect" aria-describedby="docClassSelectError">@foreach ($classifications as $classification)<option>{{ $classification }}</option>@endforeach</select><div class="invalid-feedback-custom" id="docClassSelectError" data-error-for="access_classification"></div></div>
              <div class="form-field"><label class="form-label" for="doc_issue_date">Issue date</label><input type="date" class="form-control" name="issue_date" id="doc_issue_date" aria-describedby="doc_issue_dateError"><div class="invalid-feedback-custom" id="doc_issue_dateError" data-error-for="issue_date"></div></div>
              <div class="form-field"><label class="form-label" for="doc_expiry_date">Expiry date</label><input type="date" class="form-control" name="expiry_date" id="doc_expiry_date" aria-describedby="doc_expiry_dateError"><div class="invalid-feedback-custom" id="doc_expiry_dateError" data-error-for="expiry_date"></div></div>
            </div>
            <div class="mt-3 form-field"><label class="form-label" for="doc_attachment">Document file</label><input type="file" class="form-control" name="attachment" accept=".pdf,.doc,.docx" id="doc_attachment" aria-describedby="doc_attachmentError docAttachmentHelp"><div class="form-text-help" id="docAttachmentHelp">Optional. PDF, DOC or DOCX, up to 10 MB. Select a document title in the table to download its file.</div><div class="invalid-feedback-custom" id="doc_attachmentError" data-error-for="attachment"></div></div>
            <fieldset class="form-fieldset mt-3 mb-0">
              <legend class="form-legend h6">Retention metadata</legend>
              <div class="form-grid-2">
                <div class="form-field"><label class="form-label" for="doc_retention_category">Retention category</label><select class="form-select" name="retention_category" id="doc_retention_category" aria-describedby="doc_retention_categoryError"><option>Standard (6 years)</option><option>Immigration record (statutory)</option><option>Payroll (6 years)</option><option>Permanent</option></select><div class="invalid-feedback-custom" id="doc_retention_categoryError" data-error-for="retention_category"></div></div>
                <div class="form-field"><label class="form-label" for="doc_retention_until">Retention until</label><input type="date" class="form-control" name="retention_until" id="doc_retention_until" aria-describedby="doc_retention_untilError"><div class="invalid-feedback-custom" id="doc_retention_untilError" data-error-for="retention_until"></div></div>
              </div>
              <div class="mt-3 form-field"><label class="form-label" for="doc_retention_reason">Legal / compliance basis</label><input class="form-control" name="retention_reason" placeholder="e.g. Right-to-work record retention duty" id="doc_retention_reason" aria-describedby="doc_retention_reasonError" maxlength="2000"><div class="invalid-feedback-custom" id="doc_retention_reasonError" data-error-for="retention_reason"></div></div>
            </fieldset>
            <div class="mt-3 form-field"><label class="form-label" for="doc_notes">Notes</label><textarea class="form-control" name="notes" rows="2" id="doc_notes" aria-describedby="doc_notesError" maxlength="10000"></textarea><div class="invalid-feedback-custom" id="doc_notesError" data-error-for="notes"></div></div>
          </div>
          <div class="modal-footer"><button type="button" class="btn btn-light-custom" data-bs-dismiss="modal">Cancel</button><button type="submit" class="btn btn-primary">Save Document</button></div>
        </form>
      </div>
    </div>
  </div>


