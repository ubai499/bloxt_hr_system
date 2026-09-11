@extends('layouts.master')
@section('title', 'Recruitment Bloxt People & Compliance')
@section('meta_description', 'Recruitment vacancies and candidates for Bloxt.')

@push('vendor-styles')
    <link rel="stylesheet" href="{{ asset('assets/css/vendor/dataTables.bootstrap5.min.css') }}">
@endpush

@push('styles')
    <style>
        .candidate-details-trigger { font: inherit; color: inherit; text-decoration: underline; text-underline-offset: 2px; }
        .candidate-details-trigger:hover, .candidate-details-trigger:focus-visible { color: #6B6B24; }
        .candidate-details-trigger:focus-visible { outline: 2px solid #6B6B24; outline-offset: 3px; }
        .vacancy-highlight { outline: 2px solid #6B6B24; outline-offset: 4px; }
        #vacancyList .table-app td { overflow-wrap: anywhere; }
        #recruitmentDetailsBody dd { white-space: pre-wrap; overflow-wrap: anywhere; }
    </style>
@endpush

@section('content')
    <div class="page-header-bar">
        <div class="d-flex justify-content-between align-items-start flex-wrap gap-3">
            <div>
                <h1 class="page-title">Recruitment</h1>
                <p class="page-subtitle">Open vacancies and candidate records.</p>
            </div>
            <button type="button" class="btn btn-primary" id="newVacancyBtn"><i class="bi bi-plus-lg"></i> New Vacancy</button>
        </div>
    </div>
    <div class="app-content">
        <div id="recruitmentKpis" class="kpi-grid mb-4" aria-live="polite">
            @foreach (['open' => ['Open Vacancies', 'primary'], 'in_progress' => ['Candidates In Progress', 'info'], 'offered' => ['Offers Made', 'warning'], 'hired' => ['Hired', 'success']] as $key => [$label, $accent])
                <div class="metric-card metric-accent-{{ $accent }}"><span class="metric-label">{{ $label }}</span><span class="metric-value" data-stat="{{ $key }}">{{ $payload['stats'][$key] }}</span></div>
            @endforeach
        </div>
        <div id="recruitmentPageError" class="alert alert-danger d-none" role="alert"></div>
        <div class="table-panel mb-4">
            <div class="table-toolbar">
                <div class="table-toolbar-search"><i class="bi bi-search"></i><input type="search" class="form-control" id="vacancySearch" aria-label="Search vacancies" maxlength="255" placeholder="Search job title, location or manager…"></div>
                <div class="table-toolbar-filters">
                    <select class="form-select form-select-sm" id="vacancyStatusFilter" aria-label="Filter by vacancy status" style="width:auto;">
                        <option value="">All statuses</option>
                        @foreach ($vacancyStatuses as $status)<option @selected(request('status') === $status)>{{ $status }}</option>@endforeach
                    </select>
                    <select class="form-select form-select-sm" id="vacancyDepartmentFilter" aria-label="Filter by department" style="width:auto;">
                        <option value="">All departments</option>
                        @foreach ($departments as $department)<option value="{{ $department->id }}" @selected((string) request('department') === (string) $department->id)>{{ $department->name }}</option>@endforeach
                    </select>
                </div>
                <div class="table-toolbar-actions"><button type="button" class="btn btn-sm btn-light-custom" id="exportRecruitmentBtn"><i class="bi bi-download"></i> Export</button></div>
            </div>
        </div>
        <div id="vacancyList" class="row g-4"></div>
    </div>

    <div class="modal fade" id="vacancyModal" tabindex="-1" aria-hidden="true" aria-labelledby="vacancyModalTitle">
        <div class="modal-dialog modal-lg modal-dialog-centered"><div class="modal-content">
            <form id="vacancyForm" method="POST" action="{{ route('admin.recruitment.store') }}">
                @csrf
                <div class="modal-header"><h2 class="modal-title h5" id="vacancyModalTitle">New Vacancy</h2><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button></div>
                <div class="modal-body">
                    <div id="vacancyFormError" class="alert alert-danger d-none" role="alert"></div>
                    <div class="form-grid-2">
                        <div class="form-field">
                            <label class="form-label" for="vacancyJobTitle">Job title<span class="required-indicator">*</span></label>
                            <input class="form-control" name="job_title" id="vacancyJobTitle" required maxlength="255" value="{{ old('job_title') }}" aria-describedby="job_titleError">
                            <div class="invalid-feedback-custom" id="job_titleError" data-error-for="job_title"></div>
                        </div>
                        <div class="form-field">
                            <label class="form-label" for="vacDeptSelect">Department</label>
                            <select class="form-select" name="department_id" id="vacDeptSelect" aria-describedby="department_idError">
                                <option value="">No department</option>
                                @foreach ($activeDepartments as $department)<option value="{{ $department->id }}" @selected((string) old('department_id') === (string) $department->id)>{{ $department->name }}</option>@endforeach
                            </select>
                            <div class="invalid-feedback-custom" id="department_idError" data-error-for="department_id"></div>
                        </div>
                        <div class="form-field">
                            <label class="form-label" for="vacancyHiringManager">Hiring manager</label>
                            <input class="form-control" name="hiring_manager" id="vacancyHiringManager" maxlength="255" list="hiringManagerOptions" value="{{ old('hiring_manager') }}" aria-describedby="hiring_managerError">
                            <datalist id="hiringManagerOptions">@foreach ($hiringManagers as $manager)<option value="{{ $manager }}"></option>@endforeach</datalist>
                            <div class="invalid-feedback-custom" id="hiring_managerError" data-error-for="hiring_manager"></div>
                        </div>
                        <div class="form-field">
                            <label class="form-label" for="vacTypeSelect">Employment type</label>
                            <select class="form-select" name="employment_type" id="vacTypeSelect" aria-describedby="employment_typeError">
                                <option value="">Not specified</option>
                                @foreach ($employmentTypes as $type)<option @selected(old('employment_type') === $type)>{{ $type }}</option>@endforeach
                            </select>
                            <div class="invalid-feedback-custom" id="employment_typeError" data-error-for="employment_type"></div>
                        </div>
                        <div class="form-field">
                            <label class="form-label" for="vacancyOpeningDate">Opening date</label>
                            <input type="date" class="form-control" name="opening_date" id="vacancyOpeningDate" value="{{ old('opening_date') }}" aria-describedby="opening_dateError">
                            <div class="invalid-feedback-custom" id="opening_dateError" data-error-for="opening_date"></div>
                        </div>
                        <div class="form-field">
                            <label class="form-label" for="vacancyClosingDate">Closing date</label>
                            <input type="date" class="form-control" name="closing_date" id="vacancyClosingDate" value="{{ old('closing_date') }}" aria-describedby="closing_dateError">
                            <div class="invalid-feedback-custom" id="closing_dateError" data-error-for="closing_date"></div>
                        </div>
                        <div class="form-field">
                            <label class="form-label" for="vacancySalaryMin">Salary range min (£)</label>
                            <input type="number" class="form-control" name="salary_range_min" id="vacancySalaryMin" min="0" step="0.01" value="{{ old('salary_range_min') }}" aria-describedby="salary_range_minError">
                            <div class="invalid-feedback-custom" id="salary_range_minError" data-error-for="salary_range_min"></div>
                        </div>
                        <div class="form-field">
                            <label class="form-label" for="vacancySalaryMax">Salary range max (£)</label>
                            <input type="number" class="form-control" name="salary_range_max" id="vacancySalaryMax" min="0" step="0.01" value="{{ old('salary_range_max') }}" aria-describedby="salary_range_maxError">
                            <div class="invalid-feedback-custom" id="salary_range_maxError" data-error-for="salary_range_max"></div>
                        </div>
                    </div>
                    <div class="mt-3 form-grid-2">
                        <div class="form-field">
                            <label class="form-label" for="vacancyLocation">Location</label>
                            <input class="form-control" name="location" id="vacancyLocation" maxlength="255" value="{{ old('location') }}" aria-describedby="locationError">
                            <div class="invalid-feedback-custom" id="locationError" data-error-for="location"></div>
                        </div>
                        <div class="form-field">
                            <label class="form-label" for="vacancyChannel">Recruitment channel</label>
                            <input class="form-control" name="recruitment_channel" id="vacancyChannel" maxlength="255" placeholder="e.g. Company careers page" value="{{ old('recruitment_channel') }}" aria-describedby="recruitment_channelError">
                            <div class="invalid-feedback-custom" id="recruitment_channelError" data-error-for="recruitment_channel"></div>
                        </div>
                    </div>
                    <div class="mt-3 form-field">
                        <label class="form-label" for="vacancyReason">Reason for vacancy</label>
                        <input class="form-control" name="reason_for_vacancy" id="vacancyReason" maxlength="255" value="{{ old('reason_for_vacancy') }}" aria-describedby="reason_for_vacancyError">
                        <div class="invalid-feedback-custom" id="reason_for_vacancyError" data-error-for="reason_for_vacancy"></div>
                    </div>
                    <div class="mt-3 form-field d-none" id="vacancyStatusField">
                        <label class="form-label" for="vacancyStatus">Status<span class="required-indicator">*</span></label>
                        <select class="form-select" name="status" id="vacancyStatus" aria-describedby="statusError">
                            @foreach ($vacancyStatuses as $status)<option @selected(old('status') === $status)>{{ $status }}</option>@endforeach
                        </select>
                        <div class="invalid-feedback-custom" id="statusError" data-error-for="status"></div>
                    </div>
                </div>
                <div class="modal-footer"><button type="button" class="btn btn-light-custom" data-bs-dismiss="modal">Cancel</button><button type="submit" class="btn btn-primary" id="vacancySubmit">Save Vacancy</button></div>
            </form>
        </div></div>
    </div>

    <div class="modal fade" id="candidateModal" tabindex="-1" aria-hidden="true" aria-labelledby="candidateModalTitle">
        <div class="modal-dialog modal-dialog-centered"><div class="modal-content">
            <form id="candidateForm" method="POST">
                @csrf
                <input type="hidden" name="vacancy_id" id="candidateVacancyId">
                <div class="modal-header"><h2 class="modal-title h5" id="candidateModalTitle">Add Candidate</h2><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button></div>
                <div class="modal-body">
                    <div id="candidateFormError" class="alert alert-danger d-none" role="alert"></div>
                    <div class="form-grid-2">
                        <div class="form-field">
                            <label class="form-label" for="candidateName">Candidate name<span class="required-indicator">*</span></label>
                            <input class="form-control" name="name" id="candidateName" required maxlength="255" value="{{ old('name') }}" aria-describedby="nameError">
                            <div class="invalid-feedback-custom" id="nameError" data-error-for="name"></div>
                        </div>
                        <div class="form-field">
                            <label class="form-label" for="candidateApplied">Application date</label>
                            <input type="date" class="form-control" name="application_date" id="candidateApplied" value="{{ old('application_date') }}" aria-describedby="application_dateError">
                            <div class="invalid-feedback-custom" id="application_dateError" data-error-for="application_date"></div>
                        </div>
                        <div class="form-field">
                            <label class="form-label" for="candidateSource">Source</label>
                            <input class="form-control" name="source" id="candidateSource" maxlength="255" value="{{ old('source') }}" aria-describedby="sourceError">
                            <div class="invalid-feedback-custom" id="sourceError" data-error-for="source"></div>
                        </div>
                        <div class="form-field">
                            <label class="form-label" for="candidateOutcome">Outcome</label>
                            <select class="form-select" name="outcome" id="candidateOutcome" aria-describedby="outcomeError">
                                @foreach ($outcomes as $outcome)<option @selected(old('outcome', 'In Progress') === $outcome)>{{ $outcome }}</option>@endforeach
                            </select>
                            <div class="invalid-feedback-custom" id="outcomeError" data-error-for="outcome"></div>
                        </div>
                    </div>
                    <div class="mt-3 form-field">
                        <label class="form-label" for="candidateNotes">Interview records</label>
                        <textarea class="form-control" name="interview_records" id="candidateNotes" rows="2" maxlength="5000" aria-describedby="interview_recordsError">{{ old('interview_records') }}</textarea>
                        <div class="invalid-feedback-custom" id="interview_recordsError" data-error-for="interview_records"></div>
                    </div>
                </div>
                <div class="modal-footer"><button type="button" class="btn btn-light-custom" data-bs-dismiss="modal">Cancel</button><button type="submit" class="btn btn-primary" id="candidateSubmit">Save Candidate</button></div>
            </form>
        </div></div>
    </div>

    <div class="modal fade" id="recruitmentDetailsModal" tabindex="-1" aria-hidden="true" aria-labelledby="recruitmentDetailsTitle">
        <div class="modal-dialog modal-dialog-centered"><div class="modal-content">
            <div class="modal-header"><h2 class="modal-title h5" id="recruitmentDetailsTitle">Details</h2><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button></div>
            <div class="modal-body" id="recruitmentDetailsBody" aria-live="polite"></div>
            <div class="modal-footer">
                <button type="button" class="btn btn-light-custom" data-bs-dismiss="modal">Close</button>
                <button type="button" class="btn btn-primary" id="recruitmentDetailsEdit">Edit</button>
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
        window.recruitmentPage = {{ Illuminate\Support\Js::from([
            'initial' => $payload,
            'indexUrl' => route('admin.recruitment.index'),
            'storeUrl' => route('admin.recruitment.store'),
            'exportUrl' => route('admin.recruitment.export'),
            'errors' => $errors->toArray(),
            'old' => old(),
            'openNew' => request('new') === '1' || ($errors->any() && array_key_exists('job_title', session()->getOldInput())),
            'openCandidate' => $errors->any() && array_key_exists('name', session()->getOldInput()) && ! array_key_exists('job_title', session()->getOldInput()),
            'highlight' => request('highlight'),
        ]) }};
    </script>
    <script src="{{ asset('assets/js/admin-recruitment.js') }}"></script>
@endpush
