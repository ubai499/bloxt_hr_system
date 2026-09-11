@extends('layouts.master')
@section('title', 'Payroll Bloxt People & Compliance')
@section('meta_description', 'Salary and payroll records for Bloxt.')

@push('vendor-styles')
    <link rel="stylesheet" href="{{ asset('assets/css/vendor/dataTables.bootstrap5.min.css') }}">
@endpush

@push('styles')
    <style>
        .payroll-details-trigger { font: inherit; color: inherit; text-decoration: underline; text-underline-offset: 2px; }
        .payroll-details-trigger:hover, .payroll-details-trigger:focus-visible { color: #6B6B24; }
        .payroll-details-trigger:focus-visible { outline: 2px solid #6B6B24; outline-offset: 3px; }
        #salaryTable td, #payrollTable td { overflow-wrap: anywhere; }
        #payrollDetailsBody dd { white-space: pre-wrap; overflow-wrap: anywhere; }
    </style>
@endpush

@section('content')
    <div class="page-header-bar">
        <div class="d-flex justify-content-between align-items-start flex-wrap gap-3">
            <div>
                <h1 class="page-title">Payroll</h1>
                <p class="page-subtitle">Salary records and payroll evidence.</p>
            </div>
            <div class="d-flex gap-2">
                <button type="button" class="btn btn-primary" id="newSalaryBtn"><i class="bi bi-cash-stack"></i> Record Salary Change</button>
                <button type="button" class="btn btn-primary d-none" id="newPayrollBtn"><i class="bi bi-receipt"></i> Record Payroll Evidence</button>
            </div>
        </div>
        <ul class="nav profile-tabs mt-4" id="payrollTabs" role="tablist" aria-label="Payroll workspace">
            <li class="nav-item"><button class="nav-link {{ $activeTab === 'salary' ? 'active' : '' }}" type="button" data-tab="salary" id="salaryTab" role="tab" aria-controls="tab-salary" aria-selected="{{ $activeTab === 'salary' ? 'true' : 'false' }}">Salary Records</button></li>
            <li class="nav-item"><button class="nav-link {{ $activeTab === 'payroll' ? 'active' : '' }}" type="button" data-tab="payroll" id="payrollTab" role="tab" aria-controls="tab-payroll" aria-selected="{{ $activeTab === 'payroll' ? 'true' : 'false' }}">Payroll Records</button></li>
        </ul>
    </div>
    <div class="app-content">
        <div id="payrollPageError" class="alert alert-danger d-none" role="alert"></div>
        <section id="tab-salary" role="tabpanel" aria-labelledby="salaryTab" class="{{ $activeTab === 'salary' ? '' : 'd-none' }}">
            <div class="table-panel">
                <div class="table-toolbar">
                    <div class="table-toolbar-search"><i class="bi bi-search"></i><input type="search" class="form-control" id="salarySearch" aria-label="Search salary records" maxlength="255" placeholder="Search employee…"></div>
                    <div class="table-toolbar-actions"><button type="button" class="btn btn-sm btn-light-custom" id="exportSalaryBtn"><i class="bi bi-download"></i> Export</button></div>
                </div>
                <table class="table-app is-clickable" id="salaryTable" style="width:100%;">
                    <thead><tr><th>Employee</th><th>Current Salary</th><th>Basis</th><th>Contracted Hours</th><th>Effective Date</th><th>Approved By</th><th></th></tr></thead>
                    <tbody></tbody>
                </table>
            </div>
        </section>
        <section id="tab-payroll" role="tabpanel" aria-labelledby="payrollTab" class="{{ $activeTab === 'payroll' ? '' : 'd-none' }}">
            <div class="table-panel">
                <div class="table-toolbar">
                    <div class="table-toolbar-search"><i class="bi bi-search"></i><input type="search" class="form-control" id="payrollSearch" aria-label="Search payroll evidence" maxlength="255" placeholder="Search employee, period or reference…"></div>
                    <div><span class="fw-semibold">Payroll evidence</span></div>
                    <div class="table-toolbar-actions"><button type="button" class="btn btn-sm btn-light-custom" id="exportPayrollBtn"><i class="bi bi-download"></i> Export</button></div>
                </div>
                <table class="table-app is-clickable" id="payrollTable" style="width:100%;">
                    <thead><tr><th>Employee</th><th>Period</th><th>Gross</th><th>Overtime</th><th>Bonus</th><th>Deductions</th><th>Net</th><th>Payment Date</th><th>Reference</th></tr></thead>
                    <tbody></tbody>
                </table>
            </div>
        </section>
    </div>

    <div class="modal fade" id="salaryModal" tabindex="-1" aria-hidden="true" aria-labelledby="salaryModalTitle">
        <div class="modal-dialog modal-dialog-centered"><div class="modal-content">
            <form id="salaryForm" method="POST" action="{{ route('admin.payroll.salaries.store') }}">
                @csrf
                <div class="modal-header"><h2 class="modal-title h5" id="salaryModalTitle">Record Salary Change</h2><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button></div>
                <div class="modal-body">
                    <div id="salaryFormError" class="alert alert-danger d-none" role="alert"></div>
                    <p class="form-text-help mb-3" id="salaryFormHelp"><i class="bi bi-info-circle"></i> This adds a new, dated salary record. Previous amounts are retained, never overwritten.</p>
                    <div class="form-grid-2">
                        <div class="form-field">
                            <label class="form-label" for="salaryEmployeeSelect">Employee<span class="required-indicator">*</span></label>
                            <select class="form-select" name="employee_id" id="salaryEmployeeSelect" required aria-describedby="employee_idError">
                                @forelse ($employees as $employee)<option value="{{ $employee->id }}" data-hours="{{ $employee->weekly_hours }}" @selected((string) old('employee_id', $filters['employee']) === (string) $employee->id)>{{ $employee->name }}</option>
                                @empty<option value="">No current employees available</option>@endforelse
                            </select>
                            <div class="invalid-feedback-custom" id="employee_idError" data-error-for="employee_id"></div>
                        </div>
                        <div class="form-field">
                            <label class="form-label" for="salaryAmount">Salary (£)<span class="required-indicator">*</span></label>
                            <input type="number" class="form-control" name="annual_salary" id="salaryAmount" min="0" step="0.01" required value="{{ old('annual_salary') }}" aria-describedby="annual_salaryError">
                            <div class="invalid-feedback-custom" id="annual_salaryError" data-error-for="annual_salary"></div>
                        </div>
                        <div class="form-field">
                            <label class="form-label" for="salaryFrequency">Salary basis<span class="required-indicator">*</span></label>
                            <select class="form-select" name="salary_frequency" id="salaryFrequency" required aria-describedby="salary_frequencyError">
                                @foreach ($frequencies as $frequency)<option @selected(old('salary_frequency', 'Annual') === $frequency)>{{ $frequency }}</option>@endforeach
                            </select>
                            <div class="invalid-feedback-custom" id="salary_frequencyError" data-error-for="salary_frequency"></div>
                        </div>
                        <div class="form-field">
                            <label class="form-label" for="salaryHourlyRate">Hourly rate (£)</label>
                            <input type="number" class="form-control" name="hourly_rate" id="salaryHourlyRate" min="0" step="0.01" value="{{ old('hourly_rate') }}" aria-describedby="hourly_rateError">
                            <div class="invalid-feedback-custom" id="hourly_rateError" data-error-for="hourly_rate"></div>
                        </div>
                        <div class="form-field">
                            <label class="form-label" for="salaryHours">Contracted hours</label>
                            <input type="number" class="form-control" name="contracted_hours" id="salaryHours" min="0" max="168" step="0.01" value="{{ old('contracted_hours') }}" aria-describedby="contracted_hoursError">
                            <div class="invalid-feedback-custom" id="contracted_hoursError" data-error-for="contracted_hours"></div>
                        </div>
                        <div class="form-field">
                            <label class="form-label" for="salaryEffectiveDate">Effective date<span class="required-indicator">*</span></label>
                            <input type="date" class="form-control" name="effective_date" id="salaryEffectiveDate" required value="{{ old('effective_date') }}" aria-describedby="effective_dateError">
                            <div class="invalid-feedback-custom" id="effective_dateError" data-error-for="effective_date"></div>
                        </div>
                        <div class="form-field">
                            <label class="form-label" for="salaryReason">Reason<span class="required-indicator">*</span></label>
                            <input class="form-control" name="reason" id="salaryReason" maxlength="255" required value="{{ old('reason', 'Salary change') }}" aria-describedby="reasonError">
                            <div class="invalid-feedback-custom" id="reasonError" data-error-for="reason"></div>
                        </div>
                        <div class="form-field">
                            <label class="form-label" for="salaryApprovedBy">Approved by</label>
                            <input class="form-control" name="authorised_by" id="salaryApprovedBy" maxlength="255" value="{{ old('authorised_by', auth()->user()->name) }}" aria-describedby="authorised_byError">
                            <div class="invalid-feedback-custom" id="authorised_byError" data-error-for="authorised_by"></div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer"><button type="button" class="btn btn-light-custom" data-bs-dismiss="modal">Cancel</button><button type="submit" class="btn btn-primary" id="salarySubmit">Save Salary</button></div>
            </form>
        </div></div>
    </div>

    <div class="modal fade" id="payrollModal" tabindex="-1" aria-hidden="true" aria-labelledby="payrollModalTitle">
        <div class="modal-dialog modal-dialog-centered"><div class="modal-content">
            <form id="payrollForm" method="POST" action="{{ route('admin.payroll.store') }}">
                @csrf
                <div class="modal-header"><h2 class="modal-title h5" id="payrollModalTitle">Record Payroll Evidence</h2><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button></div>
                <div class="modal-body">
                    <div id="payrollFormError" class="alert alert-danger d-none" role="alert"></div>
                    <p class="form-text-help mb-3" id="payrollFormHelp"><i class="bi bi-info-circle"></i> Record payslip evidence for a payroll period. This is not a substitute for payroll software.</p>
                    <div class="form-grid-2">
                        <div class="form-field">
                            <label class="form-label" for="payrollEmployeeSelect">Employee<span class="required-indicator">*</span></label>
                            <select class="form-select" name="employee_id" id="payrollEmployeeSelect" required aria-describedby="payroll_employee_idError">
                                @forelse ($employees as $employee)<option value="{{ $employee->id }}" data-number="{{ $employee->employee_number }}" @selected((string) old('employee_id', $filters['employee']) === (string) $employee->id)>{{ $employee->name }}</option>
                                @empty<option value="">No current employees available</option>@endforelse
                            </select>
                            <div class="invalid-feedback-custom" id="payroll_employee_idError" data-error-for="employee_id"></div>
                        </div>
                        <div class="form-field">
                            <label class="form-label" for="payrollPeriod">Period<span class="required-indicator">*</span></label>
                            <input class="form-control" name="payroll_period" id="payrollPeriod" maxlength="100" required placeholder="e.g. July 2026" value="{{ old('payroll_period') }}" aria-describedby="payroll_periodError">
                            <div class="invalid-feedback-custom" id="payroll_periodError" data-error-for="payroll_period"></div>
                        </div>
                        <div class="form-field">
                            <label class="form-label" for="payrollGross">Gross (£)<span class="required-indicator">*</span></label>
                            <input type="number" class="form-control" name="gross_salary" id="payrollGross" min="0" step="0.01" required value="{{ old('gross_salary') }}" aria-describedby="gross_salaryError">
                            <div class="invalid-feedback-custom" id="gross_salaryError" data-error-for="gross_salary"></div>
                        </div>
                        <div class="form-field">
                            <label class="form-label" for="payrollBasic">Basic (£)</label>
                            <input type="number" class="form-control" name="basic_salary" id="payrollBasic" min="0" step="0.01" value="{{ old('basic_salary') }}" aria-describedby="basic_salaryError">
                            <div class="invalid-feedback-custom" id="basic_salaryError" data-error-for="basic_salary"></div>
                        </div>
                        <div class="form-field">
                            <label class="form-label" for="payrollOvertime">Overtime (£)</label>
                            <input type="number" class="form-control" name="overtime" id="payrollOvertime" min="0" step="0.01" value="{{ old('overtime', '0') }}" aria-describedby="overtimeError">
                            <div class="invalid-feedback-custom" id="overtimeError" data-error-for="overtime"></div>
                        </div>
                        <div class="form-field">
                            <label class="form-label" for="payrollBonus">Bonus (£)</label>
                            <input type="number" class="form-control" name="bonus" id="payrollBonus" min="0" step="0.01" value="{{ old('bonus', '0') }}" aria-describedby="bonusError">
                            <div class="invalid-feedback-custom" id="bonusError" data-error-for="bonus"></div>
                        </div>
                        <div class="form-field">
                            <label class="form-label" for="payrollDeductions">Deductions (£)</label>
                            <input type="number" class="form-control" name="deductions" id="payrollDeductions" min="0" step="0.01" value="{{ old('deductions', '0') }}" aria-describedby="deductionsError">
                            <div class="invalid-feedback-custom" id="deductionsError" data-error-for="deductions"></div>
                        </div>
                        <div class="form-field">
                            <label class="form-label" for="payrollNetPreview">Net (calculated)</label>
                            <input class="form-control" id="payrollNetPreview" value="—" readonly aria-live="polite">
                        </div>
                        <div class="form-field">
                            <label class="form-label" for="payrollPaymentDate">Payment date<span class="required-indicator">*</span></label>
                            <input type="date" class="form-control" name="payment_date" id="payrollPaymentDate" required value="{{ old('payment_date') }}" aria-describedby="payment_dateError">
                            <div class="invalid-feedback-custom" id="payment_dateError" data-error-for="payment_date"></div>
                        </div>
                        <div class="form-field">
                            <label class="form-label" for="payrollReference">Reference<span class="required-indicator">*</span></label>
                            <input class="form-control" name="payroll_reference" id="payrollReference" maxlength="100" required value="{{ old('payroll_reference') }}" aria-describedby="payroll_referenceError">
                            <div class="invalid-feedback-custom" id="payroll_referenceError" data-error-for="payroll_reference"></div>
                        </div>
                    </div>
                    <div class="form-check mt-3">
                        <input class="form-check-input" type="checkbox" name="evidence_uploaded" value="1" id="payrollEvidence" @checked(old('evidence_uploaded'))>
                        <label class="form-check-label" for="payrollEvidence">Payslip or evidence uploaded</label>
                    </div>
                    <div class="mt-3 form-field">
                        <label class="form-label" for="payrollNotes">Notes</label>
                        <textarea class="form-control" name="notes" id="payrollNotes" rows="2" maxlength="5000" aria-describedby="notesError">{{ old('notes') }}</textarea>
                        <div class="invalid-feedback-custom" id="notesError" data-error-for="notes"></div>
                    </div>
                </div>
                <div class="modal-footer"><button type="button" class="btn btn-light-custom" data-bs-dismiss="modal">Cancel</button><button type="submit" class="btn btn-primary" id="payrollSubmit">Save Record</button></div>
            </form>
        </div></div>
    </div>

    <div class="modal fade" id="payrollDetailsModal" tabindex="-1" aria-hidden="true" aria-labelledby="payrollDetailsTitle">
        <div class="modal-dialog modal-dialog-centered"><div class="modal-content">
            <div class="modal-header"><h2 class="modal-title h5" id="payrollDetailsTitle">Record details</h2><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button></div>
            <div class="modal-body" id="payrollDetailsBody" aria-live="polite"></div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-danger me-auto d-none" id="payrollDetailsRemove">Remove</button>
                <button type="button" class="btn btn-light-custom" data-bs-dismiss="modal">Close</button>
                <a class="btn btn-light-custom" id="payrollDetailsProfile" href="#">Open Profile</a>
                <button type="button" class="btn btn-primary" id="payrollDetailsEdit">Edit</button>
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
        window.payrollPage = {{ Illuminate\Support\Js::from([
            'initial' => $payload,
            'filters' => $filters,
            'activeTab' => $activeTab,
            'indexUrl' => route('admin.payroll.index'),
            'storeSalaryUrl' => route('admin.payroll.salaries.store'),
            'storePayrollUrl' => route('admin.payroll.store'),
            'exportSalaryUrl' => route('admin.payroll.salaries.export'),
            'exportPayrollUrl' => route('admin.payroll.export'),
            'actor' => auth()->user()->name,
            'errors' => $errors->toArray(),
            'old' => old(),
            'openNew' => request('new'),
        ]) }};
    </script>
    <script src="{{ asset('assets/js/admin-payroll.js') }}"></script>
@endpush
