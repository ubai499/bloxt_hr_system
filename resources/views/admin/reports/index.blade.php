@extends('layouts.master')
@section('title', 'Reports Bloxt People & Compliance')
@section('meta_description', 'Reports, HR tasks and notifications for Bloxt.')

@push('vendor-styles')
    <link rel="stylesheet" href="{{ asset('assets/css/vendor/dataTables.bootstrap5.min.css') }}">
@endpush

@section('content')
    <div class="print-header">
        <h2>Bloxt Report</h2>
        <p id="printMeta"></p>
    </div>
    <div class="page-header-bar">
        <div>
            <h1 class="page-title">Reports</h1>
            <p class="page-subtitle">Generate, filter and export reports across all HR modules.</p>
        </div>
        <ul class="nav profile-tabs mt-4" id="reportsTabs" role="tablist" aria-label="Reports workspace">
            @foreach (['catalogue' => 'Report Catalogue', 'tasks' => 'HR Tasks', 'notifications' => 'Notifications'] as $tab => $label)
                <li class="nav-item"><button class="nav-link {{ $activeTab === $tab ? 'active' : '' }}" type="button" data-tab="{{ $tab }}" id="{{ $tab }}Tab" role="tab" aria-controls="tab-{{ $tab }}" aria-selected="{{ $activeTab === $tab ? 'true' : 'false' }}">{{ $label }}</button></li>
            @endforeach
        </ul>
    </div>
    <div class="app-content">
        <div id="reportsPageError" class="alert alert-danger d-none" role="alert"></div>
        <section id="tab-catalogue" class="{{ $activeTab === 'catalogue' ? '' : 'd-none' }}" role="tabpanel">
            <div class="row g-3 mb-4" id="reportCards">
                @foreach ($payload['catalogue'] as $report)
                    <div class="col-md-4 col-lg-3">
                        <button class="panel text-start w-100 h-100 border-0" type="button" data-report="{{ $report['id'] }}">
                            <i class="bi {{ $report['icon'] }}" style="font-size:1.3rem;color:#6B6B24;"></i>
                            <div class="fw-semibold mt-2">{{ $report['title'] }}</div>
                            <div class="text-meta">{{ $report['description'] }}</div>
                        </button>
                    </div>
                @endforeach
            </div>
            <div id="reportOutput"></div>
        </section>
        <section id="tab-tasks" class="{{ $activeTab === 'tasks' ? '' : 'd-none' }}" role="tabpanel">
            <div class="table-panel">
                <div class="table-toolbar">
                    <div class="table-toolbar-filters">
                        <select class="form-select form-select-sm" id="taskStatusFilter" aria-label="Filter by task status" style="width:auto;">
                            <option value="all">All statuses</option>
                            @foreach ($statuses as $status)<option>{{ $status }}</option>@endforeach
                        </select>
                    </div>
                    <div class="table-toolbar-actions"><button type="button" class="btn btn-sm btn-primary" id="newTaskBtn"><i class="bi bi-plus-lg"></i> Create Task</button></div>
                </div>
                <table class="table-app is-clickable" id="tasksTable" style="width:100%;">
                    <thead><tr><th>Title</th><th>Employee</th><th>Category</th><th>Assigned To</th><th>Priority</th><th>Due Date</th><th>Status</th></tr></thead>
                    <tbody></tbody>
                </table>
            </div>
        </section>
        <section id="tab-notifications" class="{{ $activeTab === 'notifications' ? '' : 'd-none' }}" role="tabpanel">
            <div class="table-panel">
                <div class="table-toolbar"><div><span class="fw-semibold">Notification centre</span></div></div>
                <table class="table-app" id="notificationsTable" style="width:100%;">
                    <thead><tr><th>Title</th><th>Body</th><th>Created</th><th>Status</th><th></th></tr></thead>
                    <tbody></tbody>
                </table>
            </div>
        </section>
    </div>

    <div class="modal fade" id="taskModal" tabindex="-1" aria-hidden="true" aria-labelledby="taskModalTitle">
        <div class="modal-dialog modal-dialog-centered"><div class="modal-content">
            <form id="taskForm" method="POST" action="{{ route('admin.reports.tasks.store') }}">@csrf
                <div class="modal-header"><h2 class="modal-title h5" id="taskModalTitle">Create HR Task</h2><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button></div>
                <div class="modal-body">
                    <div id="taskFormError" class="alert alert-danger d-none" role="alert"></div>
                    <div class="form-field mb-3"><label class="form-label" for="taskTitle">Title<span class="required-indicator">*</span></label><input class="form-control" name="title" id="taskTitle" required maxlength="255" aria-describedby="titleError"><div class="invalid-feedback-custom" id="titleError" data-error-for="title"></div></div>
                    <div class="form-grid-2">
                        <div class="form-field"><label class="form-label" for="taskEmployee">Employee</label><select class="form-select" name="employee_id" id="taskEmployee" aria-describedby="employee_idError"><option value="">Not employee-specific</option>@foreach ($employees as $employee)<option value="{{ $employee->id }}">{{ $employee->name }}</option>@endforeach</select><div class="invalid-feedback-custom" id="employee_idError" data-error-for="employee_id"></div></div>
                        <div class="form-field"><label class="form-label" for="taskCategory">Category</label><input class="form-control" name="category" id="taskCategory" maxlength="255" placeholder="e.g. Documents, Right to Work" aria-describedby="categoryError"><div class="invalid-feedback-custom" id="categoryError" data-error-for="category"></div></div>
                        <div class="form-field"><label class="form-label" for="taskAssigned">Assigned to</label><input class="form-control" name="assigned_to" id="taskAssigned" maxlength="255" value="{{ auth()->user()->name }}" aria-describedby="assigned_toError"><div class="invalid-feedback-custom" id="assigned_toError" data-error-for="assigned_to"></div></div>
                        <div class="form-field"><label class="form-label" for="taskPriority">Priority<span class="required-indicator">*</span></label><select class="form-select" name="priority" id="taskPriority" required aria-describedby="priorityError">@foreach ($priorities as $priority)<option>{{ $priority }}</option>@endforeach</select><div class="invalid-feedback-custom" id="priorityError" data-error-for="priority"></div></div>
                        <div class="form-field"><label class="form-label" for="taskDue">Due date</label><input type="date" class="form-control" name="due_date" id="taskDue" aria-describedby="due_dateError"><div class="invalid-feedback-custom" id="due_dateError" data-error-for="due_date"></div></div>
                        <div class="form-field"><label class="form-label" for="taskStatus">Status<span class="required-indicator">*</span></label><select class="form-select" name="status" id="taskStatus" required aria-describedby="statusError">@foreach ($statuses as $status)<option>{{ $status }}</option>@endforeach</select><div class="invalid-feedback-custom" id="statusError" data-error-for="status"></div></div>
                    </div>
                    <div class="mt-3 form-field"><label class="form-label" for="taskDescription">Description</label><textarea class="form-control" name="description" id="taskDescription" rows="2" maxlength="5000" aria-describedby="descriptionError"></textarea><div class="invalid-feedback-custom" id="descriptionError" data-error-for="description"></div></div>
                </div>
                <div class="modal-footer"><button type="button" class="btn btn-light-custom" data-bs-dismiss="modal">Cancel</button><button type="submit" class="btn btn-primary">Save Task</button></div>
            </form>
        </div></div>
    </div>
@endsection

@push('scripts')
    <script src="{{ asset('assets/js/vendor/jquery.min.js') }}"></script>
    <script src="{{ asset('assets/js/vendor/dataTables.min.js') }}"></script>
    <script src="{{ asset('assets/js/vendor/dataTables.bootstrap5.min.js') }}"></script>
    <script src="{{ asset('assets/js/app.js') }}"></script>
    <script>
        window.reportsPage = {{ Illuminate\Support\Js::from([
            'initial' => $payload,
            'activeTab' => $activeTab,
            'indexUrl' => route('admin.reports.index'),
            'showUrl' => route('admin.reports.show', ['report' => '__id__']),
            'exportUrl' => route('admin.reports.export', ['report' => '__id__']),
            'storeTaskUrl' => route('admin.reports.tasks.store'),
            'actor' => auth()->user()->name,
            'openNew' => request('new') === '1',
            'highlight' => request('highlight'),
            'statuses' => $statuses,
        ]) }};
    </script>
    <script src="{{ asset('assets/js/admin-reports.js') }}"></script>
@endpush
