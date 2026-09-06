@extends('layouts.master')
@section('title', 'Leave Bloxt People & Compliance')
@section('meta_description', 'Leave requests and balances for Bloxt.')

@push('vendor-styles')
    <link rel="stylesheet" href="{{ asset('assets/css/vendor/dataTables.bootstrap5.min.css') }}">
@endpush

@push('styles')
    <style>
        .leave-details-trigger { font: inherit; color: inherit; }
        .leave-details-trigger:focus-visible { outline: 2px solid #6B6B24; outline-offset: 3px; }
        #leaveTable td { overflow-wrap: anywhere; }
        #leaveDetailsBody dd { white-space: pre-wrap; overflow-wrap: anywhere; }
    </style>
@endpush

@section('content')
    <div class="page-header-bar">
        <div class="d-flex justify-content-between align-items-start flex-wrap gap-3">
            <div>
                <h1 class="page-title">Leave</h1>
                <p class="page-subtitle" id="leaveSubtitle">Leave requests, approvals and balances.</p>
            </div>
            <button type="button" class="btn btn-primary" id="newLeaveBtn"><i class="bi bi-plus-lg"></i> Request Leave</button>
        </div>
    </div>
    <div class="app-content">
        <div id="leaveBalanceRow" class="kpi-grid mb-4" aria-live="polite">
            @foreach (['pending' => ['Pending Requests', 'warning'], 'approved' => ['Approved Requests', 'success'], 'on_leave_today' => ['On Leave Today', 'info'], 'total' => ['Total Requests', 'primary']] as $key => [$label, $accent])
                <div class="metric-card metric-accent-{{ $accent }}"><span class="metric-label">{{ $label }}</span><span class="metric-value" data-stat="{{ $key }}">{{ $payload['stats'][$key] }}</span></div>
            @endforeach
        </div>
        <div id="leavePageError" class="alert alert-danger d-none" role="alert"></div>
        <div class="table-panel">
            <div class="table-toolbar">
                <div class="table-toolbar-filters">
                    <select class="form-select form-select-sm" id="leaveStatusFilter" aria-label="Filter by leave status" style="width:auto;">
                        <option value="">All statuses</option>
                        @foreach ($leaveStatuses as $status)<option @selected(request('status') === $status)>{{ $status }}</option>@endforeach
                    </select>
                    <select class="form-select form-select-sm" id="leaveTypeFilter" aria-label="Filter by leave type" style="width:auto;">
                        <option value="">All leave types</option>
                        @foreach ($leaveTypes as $type)<option @selected(request('type') === $type)>{{ $type }}</option>@endforeach
                    </select>
                    <select class="form-select form-select-sm" id="leaveEmployeeFilter" aria-label="Filter by employee" style="width:auto;">
                        <option value="">All employees</option>
                        @foreach ($filterEmployees as $employee)<option value="{{ $employee->id }}" @selected((string) request('employee') === (string) $employee->id)>{{ $employee->name }}</option>@endforeach
                    </select>
                </div>
                <div class="table-toolbar-actions"><button type="button" class="btn btn-sm btn-light-custom" id="exportLeaveBtn"><i class="bi bi-download"></i> Export</button></div>
            </div>
            <table class="table-app" id="leaveTable" style="width:100%;">
                <thead><tr><th scope="col">Employee</th><th scope="col">Type</th><th scope="col">From</th><th scope="col">To</th><th scope="col">Reason</th><th scope="col">Status</th><th scope="col">Approved By</th><th scope="col"><span class="visually-hidden">Actions</span></th></tr></thead>
                <tbody></tbody>
            </table>
        </div>
    </div>

    <div class="modal fade" id="leaveModal" tabindex="-1" aria-hidden="true" aria-labelledby="leaveModalTitle">
        <div class="modal-dialog modal-dialog-centered"><div class="modal-content">
            <form id="leaveForm" method="POST" action="{{ route('admin.leave.store') }}">
                @csrf
                <div class="modal-header"><h2 class="modal-title h5" id="leaveModalTitle">Request Leave</h2><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button></div>
                <div class="modal-body">
                    <div id="leaveFormError" class="alert alert-danger d-none" role="alert"></div>
                    <div class="form-field mb-3" id="leaveEmployeeField">
                        <label class="form-label" for="leaveEmployeeSelect">Employee<span class="required-indicator">*</span></label>
                        <select class="form-select" name="employee_id" id="leaveEmployeeSelect" required aria-describedby="employee_idError">
                            @forelse ($employees as $employee)<option value="{{ $employee->id }}" @selected((string) old('employee_id') === (string) $employee->id)>{{ $employee->name }}</option>
                            @empty<option value="">No current employees available</option>@endforelse
                        </select>
                        <div class="invalid-feedback-custom" id="employee_idError" data-error-for="employee_id"></div>
                    </div>
                    <div class="form-grid-2">
                        <div class="form-field">
                            <label class="form-label" for="leaveTypeSelect">Leave type<span class="required-indicator">*</span></label>
                            <select class="form-select" name="leave_type" id="leaveTypeSelect" required aria-describedby="leave_typeError">@foreach ($leaveTypes as $type)<option @selected(old('leave_type') === $type)>{{ $type }}</option>@endforeach</select>
                            <div class="invalid-feedback-custom" id="leave_typeError" data-error-for="leave_type"></div>
                        </div>
                        <div class="form-field" role="group" aria-labelledby="partialDayLabel">
                            <span class="form-label d-block" id="partialDayLabel">Partial day?</span>
                            <div class="form-check form-check-inline"><input class="form-check-input" type="radio" name="partial_day" value="1" id="partialYes" @checked(old('partial_day', '0') == '1') aria-describedby="partial_dayError partialDayHelp"><label class="form-check-label" for="partialYes">Yes</label></div>
                            <div class="form-check form-check-inline"><input class="form-check-input" type="radio" name="partial_day" value="0" id="partialNo" @checked(old('partial_day', '0') != '1') aria-describedby="partial_dayError partialDayHelp"><label class="form-check-label" for="partialNo">No</label></div>
                            <div class="form-text-help d-none" id="partialDayHelp">Half a working day (0.5 days). Use the same From and To date.</div>
                            <div class="invalid-feedback-custom" id="partial_dayError" data-error-for="partial_day"></div>
                        </div>
                        <div class="form-field">
                            <label class="form-label" for="leaveFrom">From<span class="required-indicator">*</span></label>
                            <input type="date" class="form-control" id="leaveFrom" name="from_date" required value="{{ old('from_date') }}" aria-describedby="from_dateError">
                            <div class="invalid-feedback-custom" id="from_dateError" data-error-for="from_date"></div>
                        </div>
                        <div class="form-field">
                            <label class="form-label" for="leaveTo">To<span class="required-indicator">*</span></label>
                            <input type="date" class="form-control" id="leaveTo" name="to_date" required value="{{ old('to_date') }}" aria-describedby="to_dateError">
                            <div class="invalid-feedback-custom" id="to_dateError" data-error-for="to_date"></div>
                        </div>
                    </div>
                    <div class="mt-3 form-field"><label class="form-label" for="leaveReason">Reason</label><input class="form-control" name="reason" id="leaveReason" maxlength="255" value="{{ old('reason') }}" aria-describedby="reasonError"><div class="invalid-feedback-custom" id="reasonError" data-error-for="reason"></div></div>
                    <div class="mt-3 form-field"><label class="form-label" for="leaveNotes">Notes</label><textarea class="form-control" name="notes" id="leaveNotes" rows="2" maxlength="5000" aria-describedby="notesError">{{ old('notes') }}</textarea><div class="invalid-feedback-custom" id="notesError" data-error-for="notes"></div></div>
                </div>
                <div class="modal-footer"><button type="button" class="btn btn-light-custom" data-bs-dismiss="modal">Cancel</button><button type="submit" class="btn btn-primary">Submit Request</button></div>
            </form>
        </div></div>
    </div>

    <div class="modal fade" id="leaveDetailsModal" tabindex="-1" aria-hidden="true" aria-labelledby="leaveDetailsTitle">
        <div class="modal-dialog modal-dialog-centered"><div class="modal-content">
            <div class="modal-header"><h2 class="modal-title h5" id="leaveDetailsTitle">Leave Request</h2><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button></div>
            <div class="modal-body" id="leaveDetailsBody" aria-live="polite"></div>
            <div class="modal-footer"><button type="button" class="btn btn-light-custom" data-bs-dismiss="modal">Close</button></div>
        </div></div>
    </div>
@endsection

@push('scripts')
    <script src="{{ asset('assets/js/vendor/jquery.min.js') }}"></script>
    <script src="{{ asset('assets/js/vendor/dataTables.min.js') }}"></script>
    <script src="{{ asset('assets/js/vendor/dataTables.bootstrap5.min.js') }}"></script>
    <script src="{{ asset('assets/js/app.js') }}"></script>
    <script>
        window.leavePage = {{ Illuminate\Support\Js::from([
            'initial' => $payload,
            'indexUrl' => route('admin.leave.index'),
            'exportUrl' => route('admin.leave.export'),
            'errors' => $errors->toArray(),
            'openNew' => request('new') === '1' || $errors->any(),
        ]) }};
    </script>
    <script src="{{ asset('assets/js/admin-leave.js') }}"></script>
@endpush
