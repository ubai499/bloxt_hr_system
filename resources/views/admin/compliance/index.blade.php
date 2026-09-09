@extends('layouts.master')
@section('title', 'Compliance Bloxt People & Compliance')
@section('meta_description', 'Company-wide compliance monitoring, calendar and internal reviews for Bloxt.')

@push('vendor-styles')
    <link rel="stylesheet" href="{{ asset('assets/css/vendor/dataTables.bootstrap5.min.css') }}">
@endpush

@section('content')
    <div class="page-header-bar">
        <div>
            <h1 class="page-title">Compliance</h1>
            <p class="page-subtitle">Company-wide compliance monitoring, calendar and internal review programme.</p>
        </div>
        <ul class="nav profile-tabs mt-4" id="complianceTabs" role="tablist" aria-label="Compliance workspace">
            @foreach (['dashboard' => 'Dashboard', 'calendar' => 'Compliance Calendar', 'checklist' => 'Checklist', 'reviews' => 'Internal Reviews'] as $tab => $label)
                <li class="nav-item"><button class="nav-link {{ $activeTab === $tab ? 'active' : '' }}" type="button" data-tab="{{ $tab }}" id="{{ $tab }}Tab" role="tab" aria-controls="tab-{{ $tab }}" aria-selected="{{ $activeTab === $tab ? 'true' : 'false' }}">{{ $label }}</button></li>
            @endforeach
        </ul>
    </div>
    <div class="app-content">
        <div id="compliancePageError" class="alert alert-danger d-none" role="alert"></div>
        <section id="tab-dashboard" class="{{ $activeTab === 'dashboard' ? '' : 'd-none' }}" role="tabpanel">
            <div class="kpi-grid mb-4" id="complianceKpis"></div>
            <div class="panel mb-4">
                <div class="panel-header"><div class="panel-title">All Outstanding Compliance Actions</div></div>
                <div id="complianceActions"></div>
            </div>
            <div class="disclaimer-note"><i class="bi bi-info-circle"></i><span>This system supports the organisation's internal HR record-keeping and compliance processes. It does not replace the Home Office Sponsor Management System, professional immigration advice, or current sponsor guidance.</span></div>
        </section>
        <section id="tab-calendar" class="{{ $activeTab === 'calendar' ? '' : 'd-none' }}" role="tabpanel">
            <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
                <div class="btn-group" role="group" aria-label="Calendar view">
                    <button type="button" class="btn btn-sm btn-primary" id="viewMonthBtn">Month</button>
                    <button type="button" class="btn btn-sm btn-light-custom" id="viewListBtn">List</button>
                </div>
                <div class="d-flex align-items-center gap-2" id="monthNav">
                    <button type="button" class="btn btn-sm btn-light-custom" id="prevMonthBtn" aria-label="Previous month"><i class="bi bi-chevron-left"></i></button>
                    <span class="fw-semibold" id="monthLabel"></span>
                    <button type="button" class="btn btn-sm btn-light-custom" id="nextMonthBtn" aria-label="Next month"><i class="bi bi-chevron-right"></i></button>
                </div>
            </div>
            <div id="calendarBody"></div>
        </section>
        <section id="tab-checklist" class="{{ $activeTab === 'checklist' ? '' : 'd-none' }}" role="tabpanel">
            <div class="form-field mb-4" style="max-width:360px;">
                <label class="form-label" for="checklistEmployeeSelect">Select employee</label>
                <select class="form-select" id="checklistEmployeeSelect"></select>
            </div>
            <div class="row g-4">
                <div class="col-lg-7"><div class="panel"><div class="panel-header"><div class="panel-title">Employee Record Checklist</div></div><div class="checklist-group" id="employeeChecklist"></div></div></div>
                <div class="col-lg-5"><div class="panel"><div class="panel-header"><div class="panel-title">Organisation Checklist</div></div><div class="checklist-group">
                    @foreach (['Company details current', 'Key personnel current', 'Sponsor information reviewed', 'Work locations current', 'Employee records reviewed', 'Reporting register reviewed', 'Document retention reviewed'] as $label)
                        <div class="checklist-item"><input class="form-check-input" type="checkbox" disabled checked><span class="small">{{ $label }}</span></div>
                    @endforeach
                </div></div></div>
            </div>
        </section>
        <section id="tab-reviews" class="{{ $activeTab === 'reviews' ? '' : 'd-none' }}" role="tabpanel">
            <div class="table-panel">
                <div class="table-toolbar"><div><span class="fw-semibold">Internal HR Compliance Reviews</span></div><div class="table-toolbar-actions"><button type="button" class="btn btn-sm btn-primary" id="newReviewBtn"><i class="bi bi-plus-lg"></i> New Review</button></div></div>
                <table class="table-app is-clickable" id="reviewsTable" style="width:100%;">
                    <thead><tr><th>Review #</th><th>Date</th><th>Reviewer</th><th>Area</th><th>Issues Found</th><th>Result</th><th>Due Date</th></tr></thead>
                    <tbody></tbody>
                </table>
            </div>
        </section>
    </div>

    <div class="modal fade" id="reviewModal" tabindex="-1" aria-hidden="true" aria-labelledby="reviewModalTitle">
        <div class="modal-dialog modal-lg modal-dialog-centered"><div class="modal-content">
            <form id="reviewForm" method="POST" action="{{ route('admin.compliance.store') }}">@csrf
                <div class="modal-header"><h2 class="modal-title h5" id="reviewModalTitle">New Internal Compliance Review</h2><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button></div>
                <div class="modal-body">
                    <div id="reviewFormError" class="alert alert-danger d-none" role="alert"></div>
                    <div class="form-grid-2">
                        <div class="form-field"><label class="form-label" for="reviewNumber">Review number<span class="required-indicator">*</span></label><input class="form-control" name="review_number" id="reviewNumber" required maxlength="50" aria-describedby="review_numberError"><div class="invalid-feedback-custom" id="review_numberError" data-error-for="review_number"></div></div>
                        <div class="form-field"><label class="form-label" for="reviewDate">Review date<span class="required-indicator">*</span></label><input type="date" class="form-control" name="review_date" id="reviewDate" required aria-describedby="review_dateError"><div class="invalid-feedback-custom" id="review_dateError" data-error-for="review_date"></div></div>
                        <div class="form-field"><label class="form-label" for="reviewer">Reviewer</label><input class="form-control" name="reviewer" id="reviewer" maxlength="255" value="{{ auth()->user()->name }}" aria-describedby="reviewerError"><div class="invalid-feedback-custom" id="reviewerError" data-error-for="reviewer"></div></div>
                        <div class="form-field"><label class="form-label" for="reviewArea">Area</label><input class="form-control" name="area" id="reviewArea" maxlength="255" placeholder="e.g. Right to work records" aria-describedby="areaError"><div class="invalid-feedback-custom" id="areaError" data-error-for="area"></div></div>
                        <div class="form-field"><label class="form-label" for="reviewSampled">Employees sampled</label><input type="number" min="0" class="form-control" name="employees_sampled" id="reviewSampled" aria-describedby="employees_sampledError"><div class="invalid-feedback-custom" id="employees_sampledError" data-error-for="employees_sampled"></div></div>
                        <div class="form-field"><label class="form-label" for="reviewDue">Due date</label><input type="date" class="form-control" name="due_date" id="reviewDue" aria-describedby="due_dateError"><div class="invalid-feedback-custom" id="due_dateError" data-error-for="due_date"></div></div>
                    </div>
                    <div class="mt-3 form-field"><label class="form-label" for="reviewRecords">Records reviewed</label><input class="form-control" name="records_reviewed" id="reviewRecords" maxlength="255" aria-describedby="records_reviewedError"><div class="invalid-feedback-custom" id="records_reviewedError" data-error-for="records_reviewed"></div></div>
                    <div class="mt-3 form-field"><label class="form-label" for="reviewIssues">Issues found</label><textarea class="form-control" name="issues_found" id="reviewIssues" rows="2" maxlength="5000" aria-describedby="issues_foundError"></textarea><div class="invalid-feedback-custom" id="issues_foundError" data-error-for="issues_found"></div></div>
                    <div class="mt-3 form-field"><label class="form-label" for="reviewActions">Actions required</label><textarea class="form-control" name="actions_required" id="reviewActions" rows="2" maxlength="5000" aria-describedby="actions_requiredError"></textarea><div class="invalid-feedback-custom" id="actions_requiredError" data-error-for="actions_required"></div></div>
                    <div class="mt-3 form-grid-2">
                        <div class="form-field"><label class="form-label" for="reviewResponsible">Responsible person</label><input class="form-control" name="responsible_person" id="reviewResponsible" maxlength="255" aria-describedby="responsible_personError"><div class="invalid-feedback-custom" id="responsible_personError" data-error-for="responsible_person"></div></div>
                        <div class="form-field"><label class="form-label" for="reviewResult">Result<span class="required-indicator">*</span></label><select class="form-select" name="result" id="reviewResult" required aria-describedby="resultError">@foreach ($results as $result)<option>{{ $result }}</option>@endforeach</select><div class="invalid-feedback-custom" id="resultError" data-error-for="result"></div></div>
                    </div>
                </div>
                <div class="modal-footer"><button type="button" class="btn btn-light-custom" data-bs-dismiss="modal">Cancel</button><button type="submit" class="btn btn-primary">Save Review</button></div>
            </form>
        </div></div>
    </div>

    <div class="modal fade" id="reviewDetailsModal" tabindex="-1" aria-hidden="true" aria-labelledby="reviewDetailsTitle">
        <div class="modal-dialog modal-dialog-centered"><div class="modal-content">
            <div class="modal-header"><h2 class="modal-title h5" id="reviewDetailsTitle">Internal Review</h2><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button></div>
            <div class="modal-body" id="reviewDetailsBody" aria-live="polite"></div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-danger me-auto" id="reviewDetailsRemove">Remove</button>
                <button type="button" class="btn btn-light-custom" data-bs-dismiss="modal">Close</button>
                <button type="button" class="btn btn-primary" id="reviewDetailsEdit">Edit</button>
            </div>
        </div></div>
    </div>
@endsection

@push('scripts')
    <script src="{{ asset('assets/js/vendor/jquery.min.js') }}"></script>
    <script src="{{ asset('assets/js/vendor/dataTables.min.js') }}"></script>
    <script src="{{ asset('assets/js/vendor/dataTables.bootstrap5.min.js') }}"></script>
    <script src="{{ asset('assets/js/app.js') }}"></script>
    <script>
        window.compliancePage = {{ Illuminate\Support\Js::from([
            'initial' => $payload,
            'activeTab' => $activeTab,
            'indexUrl' => route('admin.compliance.index'),
            'storeUrl' => route('admin.compliance.store'),
            'actor' => auth()->user()->name,
            'openNew' => request('new') === '1',
        ]) }};
    </script>
    <script src="{{ asset('assets/js/admin-compliance.js') }}"></script>
@endpush
