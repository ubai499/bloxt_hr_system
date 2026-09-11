@extends('layouts.master')
@section('title', 'Immigration Records Bloxt People & Compliance')
@section('meta_description', 'Time-limited immigration permissions for Bloxt.')

@push('vendor-styles')
    <link rel="stylesheet" href="{{ asset('assets/css/vendor/dataTables.bootstrap5.min.css') }}">
@endpush

@push('styles')
    <style>
        .imm-details-trigger { font: inherit; color: inherit; text-decoration: underline; text-underline-offset: 2px; }
        .imm-details-trigger:hover, .imm-details-trigger:focus-visible { color: #6B6B24; }
        .imm-details-trigger:focus-visible { outline: 2px solid #6B6B24; outline-offset: 3px; }
        #immTable td { overflow-wrap: anywhere; }
        #immDetailsBody dd { white-space: pre-wrap; overflow-wrap: anywhere; }
    </style>
@endpush

@section('content')
    <div class="page-header-bar">
        <div class="d-flex justify-content-between align-items-start flex-wrap gap-3">
            <div>
                <h1 class="page-title">Immigration Records</h1>
                <p class="page-subtitle">Time-limited immigration permissions and expiry monitoring across the workforce.</p>
            </div>
            <button type="button" class="btn btn-primary" id="newImmBtn"><i class="bi bi-passport"></i> Record Immigration Permission</button>
        </div>
    </div>
    <div class="app-content">
        <div id="immKpiRow" class="kpi-grid mb-4" aria-live="polite">
            @foreach (['total' => ['Permissions Tracked', 'primary'], 'expiring' => ['Nearing Expiry', 'warning'], 'expired' => ['Expired Permissions', 'danger'], 'indefinite' => ['No Expiry Recorded', 'neutral']] as $key => [$label, $accent])
                <div class="metric-card metric-accent-{{ $payload['stats'][$key] && $accent !== 'neutral' ? $accent : 'neutral' }}"><span class="metric-label">{{ $label }}</span><span class="metric-value" data-stat="{{ $key }}">{{ $payload['stats'][$key] }}</span></div>
            @endforeach
        </div>
        <div id="immPageError" class="alert alert-danger d-none" role="alert"></div>
        <div class="table-panel">
            <div class="table-toolbar">
                <div class="table-toolbar-search"><i class="bi bi-search"></i><input type="search" class="form-control" id="immSearch" aria-label="Search employee" maxlength="255" placeholder="Search employee…"></div>
                <div class="table-toolbar-filters">
                    <select class="form-select form-select-sm" id="immStatusFilter" aria-label="Filter by immigration status" style="width:auto;">
                        <option value="all">All statuses</option>
                        @foreach ($statuses as $status)<option @selected($filters['status'] === $status)>{{ $status }}</option>@endforeach
                    </select>
                </div>
                <div class="table-toolbar-actions"><button type="button" class="btn btn-sm btn-light-custom" id="exportImmBtn"><i class="bi bi-download"></i> Export</button></div>
            </div>
            <table class="table-app is-clickable" id="immTable" style="width:100%;">
                <thead><tr><th>Employee</th><th>Nationality</th><th>Immigration Category</th><th>Permission Start</th><th>Permission Expiry</th><th>Status</th><th>Restrictions</th><th>Responsible Person</th></tr></thead>
                <tbody></tbody>
            </table>
        </div>
    </div>

    <div class="modal fade" id="immModal" tabindex="-1" aria-hidden="true" aria-labelledby="immModalTitle">
        <div class="modal-dialog modal-dialog-centered"><div class="modal-content">
            <form id="immForm" method="POST" action="{{ route('admin.immigration.store') }}">
                @csrf
                <div class="modal-header"><h2 class="modal-title h5" id="immModalTitle">Record Immigration Permission</h2><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button></div>
                <div class="modal-body">
                    <div id="immFormError" class="alert alert-danger d-none" role="alert"></div>
                    <p class="form-text-help mb-3" id="immFormHelp"><i class="bi bi-info-circle"></i> This adds a new, dated permission record. Previous checks for this employee are retained, never overwritten.</p>
                    <div class="form-grid-2">
                        <div class="form-field">
                            <label class="form-label" for="immEmployeeSelect">Employee<span class="required-indicator">*</span></label>
                            <select class="form-select" name="employee_id" id="immEmployeeSelect" required aria-describedby="employee_idError">
                                @forelse ($employees as $employee)<option value="{{ $employee->id }}" @selected((string) old('employee_id', $filters['employee']) === (string) $employee->id)>{{ $employee->name }}</option>
                                @empty<option value="">No current employees available</option>@endforelse
                            </select>
                            <div class="invalid-feedback-custom" id="employee_idError" data-error-for="employee_id"></div>
                        </div>
                        <div class="form-field">
                            <label class="form-label" for="immCategory">Immigration permission category<span class="required-indicator">*</span></label>
                            <input class="form-control" name="immigration_category" id="immCategory" required maxlength="255" placeholder="e.g. Skilled Worker, Pre-Settled Status" value="{{ old('immigration_category') }}" aria-describedby="immigration_categoryError">
                            <div class="invalid-feedback-custom" id="immigration_categoryError" data-error-for="immigration_category"></div>
                        </div>
                        <div class="form-field">
                            <label class="form-label" for="immCheckDate">Check date<span class="required-indicator">*</span></label>
                            <input type="date" class="form-control" name="check_date" id="immCheckDate" required value="{{ old('check_date') }}" aria-describedby="check_dateError">
                            <div class="invalid-feedback-custom" id="check_dateError" data-error-for="check_date"></div>
                        </div>
                        <div class="form-field">
                            <label class="form-label" for="immMethodSelect">Check method<span class="required-indicator">*</span></label>
                            <select class="form-select" name="check_method" id="immMethodSelect" required aria-describedby="check_methodError">
                                @foreach ($methods as $method)<option @selected(old('check_method', 'Online Home Office check') === $method)>{{ $method }}</option>@endforeach
                            </select>
                            <div class="invalid-feedback-custom" id="check_methodError" data-error-for="check_method"></div>
                        </div>
                        <div class="form-field">
                            <label class="form-label" for="immPermissionStart">Permission start</label>
                            <input type="date" class="form-control" name="permission_start" id="immPermissionStart" value="{{ old('permission_start') }}" aria-describedby="permission_startError">
                            <div class="invalid-feedback-custom" id="permission_startError" data-error-for="permission_start"></div>
                        </div>
                        <div class="form-field">
                            <label class="form-label" for="immPermissionExpiry">Permission expiry</label>
                            <input type="date" class="form-control" name="permission_expiry" id="immPermissionExpiry" value="{{ old('permission_expiry') }}" aria-describedby="permission_expiryError">
                            <div class="invalid-feedback-custom" id="permission_expiryError" data-error-for="permission_expiry"></div>
                        </div>
                        <div class="form-field">
                            <label class="form-label" for="immRestrictions">Restrictions, if any</label>
                            <input class="form-control" name="restrictions" id="immRestrictions" maxlength="255" value="{{ old('restrictions') }}" aria-describedby="restrictionsError">
                            <div class="invalid-feedback-custom" id="restrictionsError" data-error-for="restrictions"></div>
                        </div>
                        <div class="form-field">
                            <label class="form-label" for="immPerformedBy">Performed by</label>
                            <input class="form-control" name="performed_by" id="immPerformedBy" maxlength="255" value="{{ old('performed_by', auth()->user()->name) }}" aria-describedby="performed_byError">
                            <div class="invalid-feedback-custom" id="performed_byError" data-error-for="performed_by"></div>
                        </div>
                        <div class="form-field" role="group" aria-labelledby="immFollowUpLabel">
                            <span class="form-label d-block" id="immFollowUpLabel">Follow-up required?</span>
                            <div class="form-check form-check-inline"><input class="form-check-input" type="radio" name="follow_up_required" value="1" id="immFollowUpYes" @checked(old('follow_up_required') == '1')><label class="form-check-label" for="immFollowUpYes">Yes</label></div>
                            <div class="form-check form-check-inline"><input class="form-check-input" type="radio" name="follow_up_required" value="0" id="immFollowUpNo" @checked(old('follow_up_required', '0') != '1')><label class="form-check-label" for="immFollowUpNo">No</label></div>
                            <div class="invalid-feedback-custom" id="follow_up_requiredError" data-error-for="follow_up_required"></div>
                        </div>
                        <div class="form-field">
                            <label class="form-label" for="immNextCheck">Next check date</label>
                            <input type="date" class="form-control" name="next_check_date" id="immNextCheck" value="{{ old('next_check_date') }}" aria-describedby="next_check_dateError">
                            <div class="invalid-feedback-custom" id="next_check_dateError" data-error-for="next_check_date"></div>
                        </div>
                    </div>
                    <div class="mt-3 form-field">
                        <label class="form-label" for="immEvidence">Evidence reference</label>
                        <input class="form-control" name="evidence_reference" id="immEvidence" maxlength="255" value="{{ old('evidence_reference') }}" aria-describedby="evidence_referenceError">
                        <div class="invalid-feedback-custom" id="evidence_referenceError" data-error-for="evidence_reference"></div>
                    </div>
                    <div class="mt-3 form-field">
                        <label class="form-label" for="immNotes">Notes</label>
                        <textarea class="form-control" name="notes" id="immNotes" rows="2" maxlength="5000" aria-describedby="notesError">{{ old('notes') }}</textarea>
                        <div class="invalid-feedback-custom" id="notesError" data-error-for="notes"></div>
                    </div>
                </div>
                <div class="modal-footer"><button type="button" class="btn btn-light-custom" data-bs-dismiss="modal">Cancel</button><button type="submit" class="btn btn-primary" id="immSubmit">Save Permission</button></div>
            </form>
        </div></div>
    </div>

    <div class="modal fade" id="immDetailsModal" tabindex="-1" aria-hidden="true" aria-labelledby="immDetailsTitle">
        <div class="modal-dialog modal-dialog-centered"><div class="modal-content">
            <div class="modal-header"><h2 class="modal-title h5" id="immDetailsTitle">Immigration Permission</h2><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button></div>
            <div class="modal-body" id="immDetailsBody" aria-live="polite"></div>
            <div class="modal-footer">
                <button type="button" class="btn btn-light-custom" data-bs-dismiss="modal">Close</button>
                <a class="btn btn-light-custom" id="immDetailsProfile" href="#">Open Profile</a>
                <button type="button" class="btn btn-primary" id="immDetailsEdit">Edit</button>
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
        window.immigrationPage = {{ Illuminate\Support\Js::from([
            'initial' => $payload,
            'filters' => $filters,
            'indexUrl' => route('admin.immigration.index'),
            'storeUrl' => route('admin.immigration.store'),
            'exportUrl' => route('admin.immigration.export'),
            'actor' => auth()->user()->name,
            'errors' => $errors->toArray(),
            'old' => old(),
            'openNew' => request('new') === '1' || ($errors->any() && array_key_exists('immigration_category', session()->getOldInput())),
        ]) }};
    </script>
    <script src="{{ asset('assets/js/admin-immigration.js') }}"></script>
@endpush
