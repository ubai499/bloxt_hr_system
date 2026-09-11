@extends('layouts.master')
@section('title', 'Right to Work Bloxt People & Compliance')
@section('meta_description', 'Right to work monitoring for Bloxt.')

@push('vendor-styles')
    <link rel="stylesheet" href="{{ asset('assets/css/vendor/dataTables.bootstrap5.min.css') }}">
@endpush

@push('styles')
    <style>
        .rtw-details-trigger { font: inherit; color: inherit; text-decoration: underline; text-underline-offset: 2px; }
        .rtw-details-trigger:hover, .rtw-details-trigger:focus-visible { color: #6B6B24; }
        .rtw-details-trigger:focus-visible { outline: 2px solid #6B6B24; outline-offset: 3px; }
        #rtwTable td { overflow-wrap: anywhere; }
        #rtwDetailsBody dd { white-space: pre-wrap; overflow-wrap: anywhere; }
    </style>
@endpush

@section('content')
    <div class="page-header-bar">
        <div class="d-flex justify-content-between align-items-start flex-wrap gap-3">
            <div>
                <h1 class="page-title">Right to Work</h1>
                <p class="page-subtitle">Right-to-work check status and follow-up monitoring across the workforce.</p>
            </div>
            <button type="button" class="btn btn-primary" id="newRtwBtn"><i class="bi bi-patch-check"></i> Record Right-to-Work Check</button>
        </div>
    </div>
    <div class="app-content">
        <div id="rtwKpiRow" class="kpi-grid mb-4" aria-live="polite">
            @foreach (['total' => ['Total Tracked', 'primary'], 'due' => ['Checks Due / Expiring', 'warning'], 'expired' => ['Expired Permissions', 'danger'], 'missing' => ['Evidence Missing', 'danger']] as $key => [$label, $accent])
                <div class="metric-card metric-accent-{{ $payload['stats'][$key] ? $accent : 'neutral' }}"><span class="metric-label">{{ $label }}</span><span class="metric-value" data-stat="{{ $key }}">{{ $payload['stats'][$key] }}</span></div>
            @endforeach
        </div>
        <div id="rtwPageError" class="alert alert-danger d-none" role="alert"></div>
        <div class="table-panel">
            <div class="table-toolbar">
                <div class="table-toolbar-search"><i class="bi bi-search"></i><input type="search" class="form-control" id="rtwSearch" aria-label="Search employee" maxlength="255" placeholder="Search employee…"></div>
                <div class="table-toolbar-filters">
                    <select class="form-select form-select-sm" id="rtwStatusFilter" aria-label="Filter by right-to-work status" style="width:auto;">
                        <option value="all">All statuses</option>
                        @foreach ($statuses as $status)<option @selected($filters['status'] === $status)>{{ $status }}</option>@endforeach
                    </select>
                </div>
                <div class="table-toolbar-actions"><button type="button" class="btn btn-sm btn-light-custom" id="exportRtwBtn"><i class="bi bi-download"></i> Export</button></div>
            </div>
            <table class="table-app is-clickable" id="rtwTable" style="width:100%;">
                <thead><tr><th>Employee</th><th>Nationality</th><th>Current Status</th><th>Check Method</th><th>Last Check</th><th>Permission Expiry</th><th>Next Follow-up</th><th>Evidence</th><th>Responsible Person</th></tr></thead>
                <tbody></tbody>
            </table>
        </div>
    </div>

    <div class="modal fade" id="rtwModal" tabindex="-1" aria-hidden="true" aria-labelledby="rtwModalTitle">
        <div class="modal-dialog modal-dialog-centered"><div class="modal-content">
            <form id="rtwForm" method="POST" action="{{ route('admin.right-to-work.store') }}">
                @csrf
                <div class="modal-header"><h2 class="modal-title h5" id="rtwModalTitle">Record Right-to-Work Check</h2><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button></div>
                <div class="modal-body">
                    <div id="rtwFormError" class="alert alert-danger d-none" role="alert"></div>
                    <p class="form-text-help mb-3" id="rtwFormHelp"><i class="bi bi-info-circle"></i> This adds a new, dated check record. Previous checks for this employee are retained, never overwritten.</p>
                    <div class="form-grid-2">
                        <div class="form-field">
                            <label class="form-label" for="rtwEmployeeSelect">Employee<span class="required-indicator">*</span></label>
                            <select class="form-select" name="employee_id" id="rtwEmployeeSelect" required aria-describedby="employee_idError">
                                @forelse ($employees as $employee)<option value="{{ $employee->id }}" @selected((string) old('employee_id', $filters['employee']) === (string) $employee->id)>{{ $employee->name }}</option>
                                @empty<option value="">No current employees available</option>@endforelse
                            </select>
                            <div class="invalid-feedback-custom" id="employee_idError" data-error-for="employee_id"></div>
                        </div>
                        <div class="form-field">
                            <label class="form-label" for="rtwCheckDate">Check date<span class="required-indicator">*</span></label>
                            <input type="date" class="form-control" name="check_date" id="rtwCheckDate" required value="{{ old('check_date') }}" aria-describedby="check_dateError">
                            <div class="invalid-feedback-custom" id="check_dateError" data-error-for="check_date"></div>
                        </div>
                        <div class="form-field">
                            <label class="form-label" for="rtwMethodSelect">Check method<span class="required-indicator">*</span></label>
                            <select class="form-select" name="check_method" id="rtwMethodSelect" required aria-describedby="check_methodError">
                                @foreach ($methods as $method)<option @selected(old('check_method') === $method)>{{ $method }}</option>@endforeach
                            </select>
                            <div class="invalid-feedback-custom" id="check_methodError" data-error-for="check_method"></div>
                        </div>
                        <div class="form-field">
                            <label class="form-label" for="rtwPerformedBy">Performed by</label>
                            <input class="form-control" name="performed_by" id="rtwPerformedBy" maxlength="255" value="{{ old('performed_by', auth()->user()->name) }}" aria-describedby="performed_byError">
                            <div class="invalid-feedback-custom" id="performed_byError" data-error-for="performed_by"></div>
                        </div>
                        <div class="form-field">
                            <label class="form-label" for="rtwCategory">Immigration permission category</label>
                            <input class="form-control" name="immigration_category" id="rtwCategory" maxlength="255" placeholder="e.g. British Citizen, Skilled Worker" value="{{ old('immigration_category') }}" aria-describedby="immigration_categoryError">
                            <div class="invalid-feedback-custom" id="immigration_categoryError" data-error-for="immigration_category"></div>
                        </div>
                        <div class="form-field">
                            <label class="form-label" for="rtwRestrictions">Restrictions, if any</label>
                            <input class="form-control" name="restrictions" id="rtwRestrictions" maxlength="255" value="{{ old('restrictions') }}" aria-describedby="restrictionsError">
                            <div class="invalid-feedback-custom" id="restrictionsError" data-error-for="restrictions"></div>
                        </div>
                        <div class="form-field">
                            <label class="form-label" for="rtwPermissionStart">Permission start</label>
                            <input type="date" class="form-control" name="permission_start" id="rtwPermissionStart" value="{{ old('permission_start') }}" aria-describedby="permission_startError">
                            <div class="invalid-feedback-custom" id="permission_startError" data-error-for="permission_start"></div>
                        </div>
                        <div class="form-field">
                            <label class="form-label" for="rtwPermissionExpiry">Permission expiry</label>
                            <input type="date" class="form-control" name="permission_expiry" id="rtwPermissionExpiry" value="{{ old('permission_expiry') }}" aria-describedby="permission_expiryError">
                            <div class="invalid-feedback-custom" id="permission_expiryError" data-error-for="permission_expiry"></div>
                        </div>
                        <div class="form-field" role="group" aria-labelledby="rtwFollowUpLabel">
                            <span class="form-label d-block" id="rtwFollowUpLabel">Follow-up required?</span>
                            <div class="form-check form-check-inline"><input class="form-check-input" type="radio" name="follow_up_required" value="1" id="rtwFollowUpYes" @checked(old('follow_up_required') == '1') aria-describedby="follow_up_requiredError"><label class="form-check-label" for="rtwFollowUpYes">Yes</label></div>
                            <div class="form-check form-check-inline"><input class="form-check-input" type="radio" name="follow_up_required" value="0" id="rtwFollowUpNo" @checked(old('follow_up_required', '0') != '1') aria-describedby="follow_up_requiredError"><label class="form-check-label" for="rtwFollowUpNo">No</label></div>
                            <div class="invalid-feedback-custom" id="follow_up_requiredError" data-error-for="follow_up_required"></div>
                        </div>
                        <div class="form-field">
                            <label class="form-label" for="rtwNextCheck">Next check date</label>
                            <input type="date" class="form-control" name="next_check_date" id="rtwNextCheck" value="{{ old('next_check_date') }}" aria-describedby="next_check_dateError">
                            <div class="invalid-feedback-custom" id="next_check_dateError" data-error-for="next_check_date"></div>
                        </div>
                    </div>
                    <div class="mt-3 form-field">
                        <label class="form-label" for="rtwEvidence">Evidence reference</label>
                        <input class="form-control" name="evidence_reference" id="rtwEvidence" maxlength="255" placeholder="e.g. share code reference, document seen" value="{{ old('evidence_reference') }}" aria-describedby="evidence_referenceError">
                        <div class="invalid-feedback-custom" id="evidence_referenceError" data-error-for="evidence_reference"></div>
                    </div>
                    <div class="mt-3 form-field">
                        <label class="form-label" for="rtwNotes">Notes</label>
                        <textarea class="form-control" name="notes" id="rtwNotes" rows="2" maxlength="5000" aria-describedby="notesError">{{ old('notes') }}</textarea>
                        <div class="invalid-feedback-custom" id="notesError" data-error-for="notes"></div>
                    </div>
                </div>
                <div class="modal-footer"><button type="button" class="btn btn-light-custom" data-bs-dismiss="modal">Cancel</button><button type="submit" class="btn btn-primary" id="rtwSubmit">Save Check</button></div>
            </form>
        </div></div>
    </div>

    <div class="modal fade" id="rtwDetailsModal" tabindex="-1" aria-hidden="true" aria-labelledby="rtwDetailsTitle">
        <div class="modal-dialog modal-dialog-centered"><div class="modal-content">
            <div class="modal-header"><h2 class="modal-title h5" id="rtwDetailsTitle">Right-to-Work Check</h2><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button></div>
            <div class="modal-body" id="rtwDetailsBody" aria-live="polite"></div>
            <div class="modal-footer">
                <button type="button" class="btn btn-light-custom" data-bs-dismiss="modal">Close</button>
                <a class="btn btn-light-custom" id="rtwDetailsProfile" href="#">Open Profile</a>
                <button type="button" class="btn btn-primary" id="rtwDetailsEdit">Edit</button>
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
        window.rightToWorkPage = {{ Illuminate\Support\Js::from([
            'initial' => $payload,
            'filters' => $filters,
            'indexUrl' => route('admin.right-to-work.index'),
            'storeUrl' => route('admin.right-to-work.store'),
            'exportUrl' => route('admin.right-to-work.export'),
            'actor' => auth()->user()->name,
            'errors' => $errors->toArray(),
            'old' => old(),
            'openNew' => request('new') === '1' || ($errors->any() && array_key_exists('check_date', session()->getOldInput())),
        ]) }};
    </script>
    <script src="{{ asset('assets/js/admin-right-to-work.js') }}"></script>
@endpush
