@extends('layouts.master')
@section('title', 'Sponsor Compliance Bloxt People & Compliance')
@section('meta_description', 'Sponsor compliance workspace for Bloxt.')

@push('vendor-styles')
    <link rel="stylesheet" href="{{ asset('assets/css/vendor/dataTables.bootstrap5.min.css') }}">
@endpush

@push('styles')
    <style>
        .sponsor-details-trigger { font: inherit; color: inherit; text-decoration: underline; text-underline-offset: 2px; }
        .sponsor-details-trigger:hover, .sponsor-details-trigger:focus-visible { color: #6B6B24; }
        #workersTable td, #eventsTable td, #changesTable td, #guidanceTable td { overflow-wrap: anywhere; }
        #sponsorDetailsBody dd { white-space: pre-wrap; overflow-wrap: anywhere; }
    </style>
@endpush

@section('content')
    <div class="page-header-bar">
        <div class="d-flex justify-content-between align-items-start flex-wrap gap-3">
            <div>
                <h1 class="page-title">Sponsor Compliance Workspace</h1>
                <p class="page-subtitle">Restricted workspace for managing sponsored worker records and sponsor duties.</p>
            </div>
            <button type="button" class="btn btn-light-custom" id="openSmsBtn"><i class="bi bi-box-arrow-up-right"></i> Open Sponsor Management System</button>
        </div>
        <ul class="nav profile-tabs mt-4" id="sponsorTabs" role="tablist" aria-label="Sponsor compliance workspace">
            @foreach (['overview' => 'Overview', 'workers' => 'Sponsored Workers', 'events' => 'Reporting Register', 'changes' => 'Company Changes', 'guidance' => 'Guidance References'] as $tab => $label)
                <li class="nav-item"><button class="nav-link {{ $activeTab === $tab ? 'active' : '' }}" type="button" data-tab="{{ $tab }}" id="{{ $tab }}Tab" role="tab" aria-controls="tab-{{ $tab }}" aria-selected="{{ $activeTab === $tab ? 'true' : 'false' }}">{{ $label }}</button></li>
            @endforeach
        </ul>
    </div>
    <div class="app-content">
        <div id="sponsorPageError" class="alert alert-danger d-none" role="alert"></div>
        <section id="tab-overview" class="{{ $activeTab === 'overview' ? '' : 'd-none' }}" role="tabpanel">
            <div class="compliance-summary-strip" id="sponsorSummary">
                @foreach (['sponsored' => 'Sponsored Workers', 'review_required' => 'Review Required', 'action_required' => 'Action Required', 'rating' => 'Licence Rating'] as $key => $label)
                    <div class="summary-strip-item"><span class="summary-strip-value" data-stat="{{ $key }}">{{ $payload['stats'][$key] }}</span><span class="summary-strip-label">{{ $label }}</span></div>
                @endforeach
            </div>
            <div class="row g-4">
                <div class="col-lg-7">
                    <div class="panel mb-4">
                        <div class="panel-header"><div class="panel-title">Sponsor Licence Administration</div><span id="licenceStatusBadge"></span><button type="button" class="btn btn-sm btn-light-custom ms-auto" id="editLicenceBtn">Edit</button></div>
                        <dl class="detail-grid" id="licenceDetails"></dl>
                    </div>
                </div>
                <div class="col-lg-5">
                    <div class="panel mb-4">
                        <div class="panel-header"><div class="panel-title">Sponsored Worker Status</div></div>
                        <div id="workerStatusList"></div>
                    </div>
                    <div class="disclaimer-note"><i class="bi bi-info-circle"></i><span>This system supports the organisation's internal HR record-keeping and compliance processes. It does not replace the Home Office Sponsor Management System, professional immigration advice, or current sponsor guidance.</span></div>
                </div>
            </div>
        </section>
        <section id="tab-workers" class="{{ $activeTab === 'workers' ? '' : 'd-none' }}" role="tabpanel">
            <div class="table-panel">
                <div class="table-toolbar"><div><span class="fw-semibold">Sponsored workers</span></div><div class="table-toolbar-actions"><button type="button" class="btn btn-sm btn-primary" id="newWorkerBtn"><i class="bi bi-plus-lg"></i> Record Sponsored Worker</button></div></div>
                <table class="table-app is-clickable" id="workersTable" style="width:100%;">
                    <thead><tr><th>Employee</th><th>Role</th><th>Department</th><th>SOC Code</th><th>Salary</th><th>Right-to-Work</th><th>Contact Verified</th><th>Overall Status</th><th></th></tr></thead>
                    <tbody></tbody>
                </table>
            </div>
        </section>
        <section id="tab-events" class="{{ $activeTab === 'events' ? '' : 'd-none' }}" role="tabpanel">
            <div class="table-panel">
                <div class="table-toolbar"><div><span class="fw-semibold">Sponsor event &amp; reporting register</span></div><div class="table-toolbar-actions"><button type="button" class="btn btn-sm btn-primary" id="newEventBtn"><i class="bi bi-plus-lg"></i> Record Sponsor Event</button></div></div>
                <table class="table-app is-clickable" id="eventsTable" style="width:100%;">
                    <thead><tr><th>Worker</th><th>Event Type</th><th>Date Occurred</th><th>Details</th><th>Assigned To</th><th>Reported via SMS</th><th>Status</th></tr></thead>
                    <tbody></tbody>
                </table>
            </div>
        </section>
        <section id="tab-changes" class="{{ $activeTab === 'changes' ? '' : 'd-none' }}" role="tabpanel">
            <div class="table-panel">
                <div class="table-toolbar"><div><span class="fw-semibold">Company changes</span></div><div class="table-toolbar-actions"><button type="button" class="btn btn-sm btn-primary" id="newChangeBtn"><i class="bi bi-plus-lg"></i> Record Company Change</button></div></div>
                <table class="table-app is-clickable" id="changesTable" style="width:100%;">
                    <thead><tr><th>Date</th><th>Change Type</th><th>Description</th><th>Sponsor Impact</th><th>Report Required</th><th>Reported</th></tr></thead>
                    <tbody></tbody>
                </table>
            </div>
        </section>
        <section id="tab-guidance" class="{{ $activeTab === 'guidance' ? '' : 'd-none' }}" role="tabpanel">
            <div class="table-panel">
                <div class="table-toolbar"><div><span class="fw-semibold">Guidance references</span></div><div class="table-toolbar-actions"><button type="button" class="btn btn-sm btn-primary" id="newGuidanceBtn"><i class="bi bi-plus-lg"></i> Add Guidance</button></div></div>
                <table class="table-app is-clickable" id="guidanceTable" style="width:100%;">
                    <thead><tr><th>Title</th><th>Source</th><th>Last Reviewed</th><th>Reviewed By</th><th>Notes</th></tr></thead>
                    <tbody></tbody>
                </table>
            </div>
        </section>
    </div>

    <div class="modal fade" id="licenceModal" tabindex="-1" aria-hidden="true" aria-labelledby="licenceModalTitle">
        <div class="modal-dialog modal-dialog-centered"><div class="modal-content">
            <form id="licenceForm" method="POST" action="{{ route('admin.sponsorship.licence.update') }}">@csrf @method('PUT')
                <div class="modal-header"><h2 class="modal-title h5" id="licenceModalTitle">Edit Sponsor Licence</h2><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button></div>
                <div class="modal-body">
                    <div id="licenceFormError" class="alert alert-danger d-none" role="alert"></div>
                    <div class="form-grid-2">
                        <div class="form-field"><label class="form-label" for="licenceStatus">Status<span class="required-indicator">*</span></label><select class="form-select" name="status" id="licenceStatus" required aria-describedby="statusError">@foreach ($licenceStatuses as $status)<option>{{ $status }}</option>@endforeach</select><div class="invalid-feedback-custom" id="statusError" data-error-for="status"></div></div>
                        <div class="form-field"><label class="form-label" for="licenceReference">Licence reference</label><input class="form-control" name="reference" id="licenceReference" maxlength="100" aria-describedby="referenceError"><div class="invalid-feedback-custom" id="referenceError" data-error-for="reference"></div></div>
                        <div class="form-field"><label class="form-label" for="licenceRating">Licence rating</label><input class="form-control" name="rating" id="licenceRating" maxlength="100" aria-describedby="ratingError"><div class="invalid-feedback-custom" id="ratingError" data-error-for="rating"></div></div>
                        <div class="form-field"><label class="form-label" for="licenceStart">Licence start date</label><input type="date" class="form-control" name="start_date" id="licenceStart" aria-describedby="start_dateError"><div class="invalid-feedback-custom" id="start_dateError" data-error-for="start_date"></div></div>
                        <div class="form-field"><label class="form-label" for="licenceRenewal">Renewal review date</label><input type="date" class="form-control" name="renewal_review_date" id="licenceRenewal" aria-describedby="renewal_review_dateError"><div class="invalid-feedback-custom" id="renewal_review_dateError" data-error-for="renewal_review_date"></div></div>
                        <div class="form-field"><label class="form-label" for="licenceRoutes">Worker routes</label><input class="form-control" name="worker_routes" id="licenceRoutes" placeholder="Comma separated" aria-describedby="worker_routesError"><div class="invalid-feedback-custom" id="worker_routesError" data-error-for="worker_routes"></div></div>
                        <div class="form-field"><label class="form-label" for="licenceAO">Authorising officer</label><input class="form-control" name="authorising_officer" id="licenceAO" maxlength="255" aria-describedby="authorising_officerError"><div class="invalid-feedback-custom" id="authorising_officerError" data-error-for="authorising_officer"></div></div>
                        <div class="form-field"><label class="form-label" for="licenceKey">Key contact</label><input class="form-control" name="key_contact" id="licenceKey" maxlength="255" aria-describedby="key_contactError"><div class="invalid-feedback-custom" id="key_contactError" data-error-for="key_contact"></div></div>
                        <div class="form-field"><label class="form-label" for="licenceL1">Level 1 user</label><input class="form-control" name="level1_user" id="licenceL1" maxlength="255" aria-describedby="level1_userError"><div class="invalid-feedback-custom" id="level1_userError" data-error-for="level1_user"></div></div>
                        <div class="form-field"><label class="form-label" for="licenceL2">Level 2 users</label><input class="form-control" name="level2_users" id="licenceL2" placeholder="Comma separated" aria-describedby="level2_usersError"><div class="invalid-feedback-custom" id="level2_usersError" data-error-for="level2_users"></div></div>
                        <div class="form-field"><label class="form-label" for="licenceReviewed">Org details last reviewed</label><input type="date" class="form-control" name="org_details_last_reviewed" id="licenceReviewed" aria-describedby="org_details_last_reviewedError"><div class="invalid-feedback-custom" id="org_details_last_reviewedError" data-error-for="org_details_last_reviewed"></div></div>
                        <div class="form-field"><label class="form-label" for="licenceNextReview">Next internal review</label><input type="date" class="form-control" name="next_internal_review_date" id="licenceNextReview" aria-describedby="next_internal_review_dateError"><div class="invalid-feedback-custom" id="next_internal_review_dateError" data-error-for="next_internal_review_date"></div></div>
                    </div>
                    <div class="mt-3 form-field"><label class="form-label" for="licenceSms">SMS URL<span class="required-indicator">*</span></label><input class="form-control" name="sms_url" id="licenceSms" required maxlength="500" aria-describedby="sms_urlError"><div class="invalid-feedback-custom" id="sms_urlError" data-error-for="sms_url"></div></div>
                </div>
                <div class="modal-footer"><button type="button" class="btn btn-light-custom" data-bs-dismiss="modal">Cancel</button><button type="submit" class="btn btn-primary">Save Licence</button></div>
            </form>
        </div></div>
    </div>

    <div class="modal fade" id="workerModal" tabindex="-1" aria-hidden="true" aria-labelledby="workerModalTitle">
        <div class="modal-dialog modal-dialog-centered modal-lg"><div class="modal-content">
            <form id="workerForm" method="POST" action="{{ route('admin.sponsorship.workers.store') }}">@csrf
                <div class="modal-header"><h2 class="modal-title h5" id="workerModalTitle">Record Sponsored Worker</h2><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button></div>
                <div class="modal-body">
                    <div id="workerFormError" class="alert alert-danger d-none" role="alert"></div>
                    <div class="form-grid-2">
                        <div class="form-field"><label class="form-label" for="workerEmployee">Employee<span class="required-indicator">*</span></label><select class="form-select" name="employee_id" id="workerEmployee" required aria-describedby="employee_idError">@foreach ($employees as $employee)<option value="{{ $employee->id }}" data-title="{{ $employee->job_title }}" data-hours="{{ $employee->weekly_hours }}" data-location="{{ $employee->work_location }}">{{ $employee->name }}</option>@endforeach</select><div class="invalid-feedback-custom" id="employee_idError" data-error-for="employee_id"></div></div>
                        <div class="form-field"><label class="form-label" for="workerRoute">Worker route<span class="required-indicator">*</span></label><select class="form-select" name="worker_route" id="workerRoute" required aria-describedby="worker_routeError">@foreach ($routes as $route)<option>{{ $route }}</option>@endforeach</select><div class="invalid-feedback-custom" id="worker_routeError" data-error-for="worker_route"></div></div>
                        <div class="form-field"><label class="form-label" for="workerStatus">Sponsorship status<span class="required-indicator">*</span></label><select class="form-select" name="sponsorship_status" id="workerStatus" required aria-describedby="sponsorship_statusError">@foreach ($recordStatuses as $status)<option>{{ $status }}</option>@endforeach</select><div class="invalid-feedback-custom" id="sponsorship_statusError" data-error-for="sponsorship_status"></div></div>
                        <div class="form-field"><label class="form-label" for="workerLicence">Sponsor licence ref</label><input class="form-control" name="sponsor_licence_ref" id="workerLicence" maxlength="100" aria-describedby="sponsor_licence_refError"><div class="invalid-feedback-custom" id="sponsor_licence_refError" data-error-for="sponsor_licence_ref"></div></div>
                        <div class="form-field"><label class="form-label" for="workerCos">CoS reference</label><input class="form-control" name="cos_reference" id="workerCos" maxlength="100" aria-describedby="cos_referenceError"><div class="invalid-feedback-custom" id="cos_referenceError" data-error-for="cos_reference"></div></div>
                        <div class="form-field"><label class="form-label" for="workerCosAssigned">CoS assigned</label><input type="date" class="form-control" name="cos_assigned_date" id="workerCosAssigned" aria-describedby="cos_assigned_dateError"><div class="invalid-feedback-custom" id="cos_assigned_dateError" data-error-for="cos_assigned_date"></div></div>
                        <div class="form-field"><label class="form-label" for="workerCosStart">CoS start</label><input type="date" class="form-control" name="cos_start_date" id="workerCosStart" aria-describedby="cos_start_dateError"><div class="invalid-feedback-custom" id="cos_start_dateError" data-error-for="cos_start_date"></div></div>
                        <div class="form-field"><label class="form-label" for="workerCosEnd">CoS end</label><input type="date" class="form-control" name="cos_end_date" id="workerCosEnd" aria-describedby="cos_end_dateError"><div class="invalid-feedback-custom" id="cos_end_dateError" data-error-for="cos_end_date"></div></div>
                        <div class="form-field"><label class="form-label" for="workerSoc">SOC code</label><input class="form-control" name="soc_code" id="workerSoc" maxlength="20" aria-describedby="soc_codeError"><div class="invalid-feedback-custom" id="soc_codeError" data-error-for="soc_code"></div></div>
                        <div class="form-field"><label class="form-label" for="workerSocTitle">SOC title</label><input class="form-control" name="soc_title" id="workerSocTitle" maxlength="255" aria-describedby="soc_titleError"><div class="invalid-feedback-custom" id="soc_titleError" data-error-for="soc_title"></div></div>
                        <div class="form-field"><label class="form-label" for="workerJob">Internal job title</label><input class="form-control" name="internal_job_title" id="workerJob" maxlength="255" aria-describedby="internal_job_titleError"><div class="invalid-feedback-custom" id="internal_job_titleError" data-error-for="internal_job_title"></div></div>
                        <div class="form-field"><label class="form-label" for="workerSalary">Annual salary (£)</label><input type="number" class="form-control" name="annual_salary" id="workerSalary" min="0" step="0.01" aria-describedby="annual_salaryError"><div class="invalid-feedback-custom" id="annual_salaryError" data-error-for="annual_salary"></div></div>
                        <div class="form-field"><label class="form-label" for="workerHours">Weekly hours</label><input type="number" class="form-control" name="weekly_hours" id="workerHours" min="0" max="168" step="0.01" aria-describedby="weekly_hoursError"><div class="invalid-feedback-custom" id="weekly_hoursError" data-error-for="weekly_hours"></div></div>
                        <div class="form-field"><label class="form-label" for="workerLocation">Work location</label><input class="form-control" name="work_location" id="workerLocation" maxlength="255" aria-describedby="work_locationError"><div class="invalid-feedback-custom" id="work_locationError" data-error-for="work_location"></div></div>
                        <div class="form-field"><label class="form-label" for="workerManager">Line manager</label><select class="form-select" name="line_manager_id" id="workerManager" aria-describedby="line_manager_idError"><option value="">Not assigned</option>@foreach ($managers as $manager)<option value="{{ $manager->id }}">{{ $manager->name }}</option>@endforeach</select><div class="invalid-feedback-custom" id="line_manager_idError" data-error-for="line_manager_id"></div></div>
                        <div class="form-field"><label class="form-label" for="workerHr">HR responsible person</label><input class="form-control" name="hr_responsible_person" id="workerHr" maxlength="255" value="{{ auth()->user()->name }}" aria-describedby="hr_responsible_personError"><div class="invalid-feedback-custom" id="hr_responsible_personError" data-error-for="hr_responsible_person"></div></div>
                        <div class="form-field"><label class="form-label" for="workerReview">Next review date</label><input type="date" class="form-control" name="next_review_date" id="workerReview" aria-describedby="next_review_dateError"><div class="invalid-feedback-custom" id="next_review_dateError" data-error-for="next_review_date"></div></div>
                    </div>
                    <div class="mt-3 form-field"><label class="form-label" for="workerPattern">Work pattern</label><input class="form-control" name="work_pattern" id="workerPattern" maxlength="255" aria-describedby="work_patternError"><div class="invalid-feedback-custom" id="work_patternError" data-error-for="work_pattern"></div></div>
                    <div class="mt-3 form-field"><label class="form-label" for="workerNotes">Notes</label><textarea class="form-control" name="notes" id="workerNotes" rows="2" maxlength="5000" aria-describedby="notesError"></textarea><div class="invalid-feedback-custom" id="notesError" data-error-for="notes"></div></div>
                </div>
                <div class="modal-footer"><button type="button" class="btn btn-light-custom" data-bs-dismiss="modal">Cancel</button><button type="submit" class="btn btn-primary">Save Record</button></div>
            </form>
        </div></div>
    </div>

    <div class="modal fade" id="eventModal" tabindex="-1" aria-hidden="true" aria-labelledby="eventModalTitle">
        <div class="modal-dialog modal-dialog-centered"><div class="modal-content">
            <form id="eventForm" method="POST" action="{{ route('admin.sponsorship.events.store') }}">@csrf
                <div class="modal-header"><h2 class="modal-title h5" id="eventModalTitle">Record Sponsor Event</h2><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button></div>
                <div class="modal-body">
                    <div id="eventFormError" class="alert alert-danger d-none" role="alert"></div>
                    <div class="form-grid-2">
                        <div class="form-field"><label class="form-label" for="eventEmployee">Sponsored worker<span class="required-indicator">*</span></label><select class="form-select" name="employee_id" id="eventEmployee" required aria-describedby="event_employee_idError"></select><div class="invalid-feedback-custom" id="event_employee_idError" data-error-for="employee_id"></div></div>
                        <div class="form-field"><label class="form-label" for="eventType">Event type<span class="required-indicator">*</span></label><select class="form-select" name="event_type" id="eventType" required aria-describedby="event_typeError">@foreach ($eventTypes as $type)<option>{{ $type }}</option>@endforeach</select><div class="invalid-feedback-custom" id="event_typeError" data-error-for="event_type"></div></div>
                        <div class="form-field"><label class="form-label" for="eventOccurred">Date occurred</label><input type="date" class="form-control" name="date_occurred" id="eventOccurred" aria-describedby="date_occurredError"><div class="invalid-feedback-custom" id="date_occurredError" data-error-for="date_occurred"></div></div>
                        <div class="form-field"><label class="form-label" for="eventAware">Date HR became aware</label><input type="date" class="form-control" name="date_aware" id="eventAware" aria-describedby="date_awareError"><div class="invalid-feedback-custom" id="date_awareError" data-error-for="date_aware"></div></div>
                        <div class="form-field"><label class="form-label" for="eventAssigned">Assigned to</label><input class="form-control" name="assigned_to" id="eventAssigned" maxlength="255" value="{{ auth()->user()->name }}" aria-describedby="assigned_toError"><div class="invalid-feedback-custom" id="assigned_toError" data-error-for="assigned_to"></div></div>
                        <div class="form-field"><label class="form-label" for="eventStatus">Assessment status<span class="required-indicator">*</span></label><select class="form-select" name="status" id="eventStatus" required aria-describedby="event_statusError">@foreach ($eventStatuses as $status)<option>{{ $status }}</option>@endforeach</select><div class="invalid-feedback-custom" id="event_statusError" data-error-for="status"></div></div>
                    </div>
                    <div class="mt-3 form-field"><label class="form-label" for="eventDetails">Details<span class="required-indicator">*</span></label><textarea class="form-control" name="details" id="eventDetails" rows="2" required maxlength="5000" aria-describedby="detailsError"></textarea><div class="invalid-feedback-custom" id="detailsError" data-error-for="details"></div></div>
                    <div class="form-check mt-3"><input class="form-check-input" type="checkbox" name="reported_through_sms" value="1" id="eventSms"><label class="form-check-label" for="eventSms">Reported through SMS</label></div>
                    <div class="form-grid-2 mt-3">
                        <div class="form-field"><label class="form-label" for="eventReportedDate">Date reported</label><input type="date" class="form-control" name="date_reported" id="eventReportedDate" aria-describedby="date_reportedError"><div class="invalid-feedback-custom" id="date_reportedError" data-error-for="date_reported"></div></div>
                        <div class="form-field"><label class="form-label" for="eventReportedBy">Reported by</label><input class="form-control" name="reported_by" id="eventReportedBy" maxlength="255" aria-describedby="reported_byError"><div class="invalid-feedback-custom" id="reported_byError" data-error-for="reported_by"></div></div>
                    </div>
                    <div class="mt-3 form-field"><label class="form-label" for="eventNotes">Internal notes</label><textarea class="form-control" name="notes" id="eventNotes" rows="2" maxlength="5000" aria-describedby="event_notesError"></textarea><div class="invalid-feedback-custom" id="event_notesError" data-error-for="notes"></div></div>
                </div>
                <div class="modal-footer"><button type="button" class="btn btn-light-custom" data-bs-dismiss="modal">Cancel</button><button type="submit" class="btn btn-primary">Save Event</button></div>
            </form>
        </div></div>
    </div>

    <div class="modal fade" id="changeModal" tabindex="-1" aria-hidden="true" aria-labelledby="changeModalTitle">
        <div class="modal-dialog modal-dialog-centered"><div class="modal-content">
            <form id="changeForm" method="POST" action="{{ route('admin.sponsorship.changes.store') }}">@csrf
                <div class="modal-header"><h2 class="modal-title h5" id="changeModalTitle">Record Company Change</h2><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button></div>
                <div class="modal-body">
                    <div id="changeFormError" class="alert alert-danger d-none" role="alert"></div>
                    <div class="form-grid-2">
                        <div class="form-field"><label class="form-label" for="changeType">Change type<span class="required-indicator">*</span></label><select class="form-select" name="change_type" id="changeType" required aria-describedby="change_typeError">@foreach ($changeTypes as $type)<option>{{ $type }}</option>@endforeach</select><div class="invalid-feedback-custom" id="change_typeError" data-error-for="change_type"></div></div>
                        <div class="form-field"><label class="form-label" for="changeDate">Date<span class="required-indicator">*</span></label><input type="date" class="form-control" name="date" id="changeDate" required aria-describedby="dateError"><div class="invalid-feedback-custom" id="dateError" data-error-for="date"></div></div>
                        <div class="form-field"><label class="form-label" for="changeImpact">Sponsor impact</label><input class="form-control" name="potential_sponsor_impact" id="changeImpact" maxlength="255" aria-describedby="potential_sponsor_impactError"><div class="invalid-feedback-custom" id="potential_sponsor_impactError" data-error-for="potential_sponsor_impact"></div></div>
                        <div class="form-field"><label class="form-label" for="changeReviewed">Reviewed by</label><input class="form-control" name="reviewed_by" id="changeReviewed" maxlength="255" aria-describedby="reviewed_byError"><div class="invalid-feedback-custom" id="reviewed_byError" data-error-for="reviewed_by"></div></div>
                    </div>
                    <div class="mt-3 form-field"><label class="form-label" for="changeDescription">Description<span class="required-indicator">*</span></label><textarea class="form-control" name="description" id="changeDescription" rows="2" required maxlength="5000" aria-describedby="descriptionError"></textarea><div class="invalid-feedback-custom" id="descriptionError" data-error-for="description"></div></div>
                    <div class="form-check mt-3"><input class="form-check-input" type="checkbox" name="report_required" value="1" id="changeReportRequired"><label class="form-check-label" for="changeReportRequired">Report required</label></div>
                    <div class="form-check mt-2"><input class="form-check-input" type="checkbox" name="reported" value="1" id="changeReported"><label class="form-check-label" for="changeReported">Reported</label></div>
                    <div class="mt-3 form-field"><label class="form-label" for="changeReportedDate">Reported date</label><input type="date" class="form-control" name="reported_date" id="changeReportedDate" aria-describedby="reported_dateError"><div class="invalid-feedback-custom" id="reported_dateError" data-error-for="reported_date"></div></div>
                    <div class="mt-3 form-field"><label class="form-label" for="changeNotes">Notes</label><textarea class="form-control" name="notes" id="changeNotes" rows="2" maxlength="5000" aria-describedby="change_notesError"></textarea><div class="invalid-feedback-custom" id="change_notesError" data-error-for="notes"></div></div>
                </div>
                <div class="modal-footer"><button type="button" class="btn btn-light-custom" data-bs-dismiss="modal">Cancel</button><button type="submit" class="btn btn-primary">Save Change</button></div>
            </form>
        </div></div>
    </div>

    <div class="modal fade" id="guidanceModal" tabindex="-1" aria-hidden="true" aria-labelledby="guidanceModalTitle">
        <div class="modal-dialog modal-dialog-centered"><div class="modal-content">
            <form id="guidanceForm" method="POST" action="{{ route('admin.sponsorship.guidance.store') }}">@csrf
                <div class="modal-header"><h2 class="modal-title h5" id="guidanceModalTitle">Add Guidance Reference</h2><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button></div>
                <div class="modal-body">
                    <div id="guidanceFormError" class="alert alert-danger d-none" role="alert"></div>
                    <div class="form-grid-2">
                        <div class="form-field"><label class="form-label" for="guidanceTitle">Title<span class="required-indicator">*</span></label><input class="form-control" name="title" id="guidanceTitle" required maxlength="255" aria-describedby="titleError"><div class="invalid-feedback-custom" id="titleError" data-error-for="title"></div></div>
                        <div class="form-field"><label class="form-label" for="guidanceSource">Source</label><input class="form-control" name="source" id="guidanceSource" maxlength="255" aria-describedby="sourceError"><div class="invalid-feedback-custom" id="sourceError" data-error-for="source"></div></div>
                        <div class="form-field"><label class="form-label" for="guidanceReviewed">Last reviewed</label><input type="date" class="form-control" name="last_reviewed" id="guidanceReviewed" aria-describedby="last_reviewedError"><div class="invalid-feedback-custom" id="last_reviewedError" data-error-for="last_reviewed"></div></div>
                        <div class="form-field"><label class="form-label" for="guidanceReviewer">Reviewed by</label><input class="form-control" name="reviewed_by" id="guidanceReviewer" maxlength="255" value="{{ auth()->user()->name }}" aria-describedby="reviewed_byError"><div class="invalid-feedback-custom" id="reviewed_byError" data-error-for="reviewed_by"></div></div>
                    </div>
                    <div class="mt-3 form-field"><label class="form-label" for="guidanceUrl">URL<span class="required-indicator">*</span></label><input class="form-control" name="url" id="guidanceUrl" required maxlength="500" aria-describedby="urlError"><div class="invalid-feedback-custom" id="urlError" data-error-for="url"></div></div>
                    <div class="mt-3 form-field"><label class="form-label" for="guidanceNotes">Notes</label><textarea class="form-control" name="notes" id="guidanceNotes" rows="2" maxlength="5000" aria-describedby="guidance_notesError"></textarea><div class="invalid-feedback-custom" id="guidance_notesError" data-error-for="notes"></div></div>
                </div>
                <div class="modal-footer"><button type="button" class="btn btn-light-custom" data-bs-dismiss="modal">Cancel</button><button type="submit" class="btn btn-primary">Save Guidance</button></div>
            </form>
        </div></div>
    </div>

    <div class="modal fade" id="sponsorDetailsModal" tabindex="-1" aria-hidden="true" aria-labelledby="sponsorDetailsTitle">
        <div class="modal-dialog modal-dialog-centered"><div class="modal-content">
            <div class="modal-header"><h2 class="modal-title h5" id="sponsorDetailsTitle">Record details</h2><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button></div>
            <div class="modal-body" id="sponsorDetailsBody" aria-live="polite"></div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-danger me-auto" id="sponsorDetailsRemove">Remove</button>
                <button type="button" class="btn btn-light-custom" data-bs-dismiss="modal">Close</button>
                <a class="btn btn-light-custom d-none" id="sponsorDetailsProfile" href="#">Open Profile</a>
                <button type="button" class="btn btn-primary" id="sponsorDetailsEdit">Edit</button>
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
        window.sponsorshipPage = {{ Illuminate\Support\Js::from([
            'initial' => $payload,
            'activeTab' => $activeTab,
            'indexUrl' => route('admin.sponsorship.index'),
            'storeWorkerUrl' => route('admin.sponsorship.workers.store'),
            'storeEventUrl' => route('admin.sponsorship.events.store'),
            'storeChangeUrl' => route('admin.sponsorship.changes.store'),
            'storeGuidanceUrl' => route('admin.sponsorship.guidance.store'),
            'actor' => auth()->user()->name,
            'openNew' => request('new'),
            'employee' => request('employee'),
            'highlight' => request('highlight'),
        ]) }};
    </script>
    <script src="{{ asset('assets/js/admin-sponsorship.js') }}"></script>
@endpush
