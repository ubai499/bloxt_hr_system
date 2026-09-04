@extends('layouts.master')

@php
    $statusClass = fn (?string $status) => match ($status) {
        'Active' => 'badge-success',
        'Left' => 'badge-danger',
        'On Leave' => 'badge-warning',
        'Probation' => 'badge-info',
        default => 'badge-neutral',
    };
@endphp

@section('title', 'Employees | Bloxt HR')
@section('meta_description', 'Employee directory for Bloxt HR.')

@push('styles')
    <link rel="stylesheet" href="{{ asset('assets/css/vendor/dataTables.bootstrap5.min.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/css/vendor/responsive.bootstrap5.min.css') }}">
@endpush

@section('content')
    <div class="page-header-bar">
        <div class="d-flex justify-content-between align-items-start flex-wrap gap-3">
            <div>
                <h1 class="page-title">Employees</h1>
                <p class="page-subtitle">Directory, departments and organisation records.</p>
            </div>
            <div class="d-flex gap-2" id="employeeHeaderActions">
                @if ($activeTab === 'departments')
                    <button
                        type="button"
                        class="btn btn-primary"
                        data-bs-toggle="modal"
                        data-bs-target="#deptModal"
                        data-dept-action="create"
                    >
                        <i class="bi bi-plus-lg"></i> Add Department
                    </button>
                @else
                    <a class="btn btn-primary" href="{{ route('admin.employees.create') }}">
                        <i class="bi bi-person-plus"></i> Add Employee
                    </a>
                @endif
            </div>
        </div>

        <ul class="nav profile-tabs mt-4" id="employeeTabs">
            <li class="nav-item">
                <button type="button" class="nav-link {{ $activeTab === 'directory' ? 'active' : '' }}" data-tab="directory">Directory</button>
            </li>
            <li class="nav-item">
                <button type="button" class="nav-link {{ $activeTab === 'departments' ? 'active' : '' }}" data-tab="departments">Departments</button>
            </li>
        </ul>
    </div>

    <div class="app-content">
        @if (session('success'))
            <div class="alert alert-success" role="alert">{{ session('success') }}</div>
        @endif

        <section id="tab-directory" class="{{ $activeTab === 'directory' ? '' : 'd-none' }}">
            <div class="table-panel">
                <div class="table-toolbar">
                    <div class="table-toolbar-search">
                        <i class="bi bi-search"></i>
                        <input type="search" class="form-control" id="empSearch" placeholder="Search name, ID, email, job title..." autocomplete="off">
                    </div>

                    <div class="table-toolbar-filters">
                        <select class="form-select form-select-sm" id="filterDept" style="width:auto;">
                            <option value="all">All departments</option>
                            @foreach ($departments as $department)
                                <option value="{{ $department->id }}">{{ $department->name }}</option>
                            @endforeach
                        </select>

                        <select class="form-select form-select-sm" id="filterStatus" style="width:auto;">
                            <option value="all">All statuses</option>
                            @foreach (['Active', 'On Leave', 'Probation', 'Left'] as $status)
                                <option value="{{ strtolower($status) }}">{{ $status }}</option>
                            @endforeach
                        </select>

                        <select class="form-select form-select-sm" id="filterLocation" style="width:auto;">
                            <option value="all">All locations</option>
                            @foreach ($workLocations as $workLocation)
                                <option value="{{ strtolower($workLocation) }}">{{ $workLocation }}</option>
                            @endforeach
                        </select>

                        <select class="form-select form-select-sm" id="filterType" style="width:auto;">
                            <option value="all">All employment types</option>
                            @foreach ($employmentTypes as $employmentType)
                                <option value="{{ strtolower($employmentType) }}">{{ $employmentType }}</option>
                            @endforeach
                        </select>

                        <div class="form-check ms-2 d-flex align-items-center">
                            <input class="form-check-input me-1" type="checkbox" id="filterSponsored">
                            <label class="form-check-label small" for="filterSponsored">Sponsored only</label>
                        </div>
                    </div>

                    <div class="table-toolbar-actions">
                        <button class="btn btn-sm btn-light-custom" id="exportEmployeesBtn" type="button">
                            <i class="bi bi-download"></i> Export
                        </button>
                        <a class="btn btn-sm btn-primary" href="{{ route('admin.employees.create') }}">
                            <i class="bi bi-person-plus"></i> Add Employee
                        </a>
                    </div>
                </div>

                <div class="table-responsive">
                    <table class="table-app is-clickable" id="employeeTable" style="width:100%;">
                        <thead>
                            <tr>
                                <th>Employee ID</th>
                                <th>Employee</th>
                                <th>Job Title</th>
                                <th>Department</th>
                                <th>Manager</th>
                                <th>Employment Type</th>
                                <th>Work Location</th>
                                <th>Start Date</th>
                                <th>Status</th>
                                <th>Right-to-Work</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($employees as $employee)
                                @php
                                    $initials = collect(explode(' ', $employee->name))
                                        ->filter()
                                        ->take(2)
                                        ->map(fn ($part) => strtoupper(substr($part, 0, 1)))
                                        ->implode('');
                                @endphp
                                <tr
                                    data-href="{{ route('admin.employees.show', $employee) }}"
                                    data-department-id="{{ $employee->department_id }}"
                                    data-status="{{ strtolower($employee->status ?? '') }}"
                                    data-location="{{ strtolower($employee->work_location ?? '') }}"
                                    data-type="{{ strtolower($employee->employment_type ?? '') }}"
                                    data-sponsored="{{ $employee->latestRightToWorkCheck?->isSponsored() ? '1' : '0' }}"
                                >
                                    <td class="cell-secondary">{{ $employee->employee_number ?: '-' }}</td>
                                    <td>
                                        <div class="employee-cell">
                                            <span class="avatar-circle">{{ $initials ?: 'EM' }}</span>
                                            <div>
                                                <div class="cell-primary">{{ $employee->name }}</div>
                                                <div class="cell-secondary">{{ $employee->email }}</div>
                                            </div>
                                        </div>
                                    </td>
                                    <td>{{ $employee->job_title ?: '-' }}</td>
                                    <td>{{ $employee->departmentRecord?->name ?: '-' }}</td>
                                    <td>{{ $employee->manager?->name ?: '-' }}</td>
                                    <td>{{ $employee->employment_type ?: '-' }}</td>
                                    <td>{{ $employee->work_location ?: '-' }}</td>
                                    <td>{{ $employee->start_date?->format('d M Y') ?: '-' }}</td>
                                    <td><span class="status-badge {{ $statusClass($employee->status) }}">{{ $employee->status ?: 'Unknown' }}</span></td>
                                    <td>
                                        @php($rightToWorkStatus = $employee->latestRightToWorkCheck?->directoryStatus() ?? 'Evidence Missing')
                                        @if ($employee->latestRightToWorkCheck?->isSponsored())
                                            <span class="status-badge badge-info">Sponsored</span>
                                        @endif
                                        <span class="status-badge {{ $statusClass($rightToWorkStatus) }}">{{ $rightToWorkStatus }}</span>
                                    </td>
                                    <td class="text-end">
                                        <a href="{{ route('admin.employees.show', $employee) }}" class="btn btn-sm btn-light-custom">View</a>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="11" class="text-center py-5 text-meta">
                                        <i class="bi bi-people d-block fs-3 mb-2"></i>
                                        No employees found. Add the first employee to create their HR record and login.
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </section>

        <section id="tab-departments" class="{{ $activeTab === 'departments' ? '' : 'd-none' }}">
            <div class="table-panel">
                <div class="table-toolbar">
                    <div><span class="fw-semibold">Company departments</span></div>
                    <div class="table-toolbar-actions">
                        <button
                            class="btn btn-sm btn-primary"
                            id="addDeptBtn"
                            type="button"
                            data-bs-toggle="modal"
                            data-bs-target="#deptModal"
                            data-dept-action="create"
                        >
                            <i class="bi bi-plus-lg"></i> Add Department
                        </button>
                    </div>
                </div>

                <div class="table-responsive">
                    <table class="table-app" id="departmentTable" style="width:100%;">
                        <thead>
                            <tr>
                                <th>Department</th>
                                <th>Headcount</th>
                                <th>Status</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($departments as $department)
                                <tr>
                                    <td class="cell-primary">{{ $department->name }}</td>
                                    <td>{{ $department->employees_count }}</td>
                                    <td><span class="status-badge {{ $department->status === 'Active' ? 'badge-success' : 'badge-warning' }}">{{ $department->status }}</span></td>
                                    <td class="text-end">
                                        <button
                                            type="button"
                                            class="btn btn-sm btn-light-custom"
                                            data-bs-toggle="modal"
                                            data-bs-target="#deptModal"
                                            data-dept-action="edit"
                                            data-dept-name="{{ $department->name }}"
                                            data-dept-status="{{ $department->status }}"
                                            data-dept-update-url="{{ route('admin.departments.update', $department) }}"
                                        >
                                            <i class="bi bi-pencil"></i>
                                        </button>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="4" class="text-center py-5 text-meta">
                                        <i class="bi bi-diagram-3 d-block fs-3 mb-2"></i>
                                        No departments recorded.
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </section>
    </div>

    <div class="modal fade" id="deptModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <form id="deptForm" method="POST" action="{{ route('admin.departments.store') }}">
                    @csrf
                    <input type="hidden" id="deptFormMethod" name="_method" value="">
                    <input type="hidden" name="return_to_employee_directory" value="1">

                    <div class="modal-header">
                        <h2 class="modal-title h5">Add Department</h2>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>

                    <div class="modal-body">
                        <div class="mb-3 form-field">
                            <label class="form-label" for="deptName">Department name<span class="required-indicator">*</span></label>
                            <input type="text" class="form-control" id="deptName" name="name" required>
                            <div class="invalid-feedback-custom">
                                <i class="bi bi-exclamation-circle"></i>
                                <span>Enter a department name.</span>
                            </div>
                        </div>

                        <div class="mb-1 form-field">
                            <label class="form-label" for="deptStatus">Status</label>
                            <select class="form-select" id="deptStatus" name="status">
                                <option value="Active">Active</option>
                                <option value="Inactive">Inactive</option>
                            </select>
                        </div>
                    </div>

                    <div class="modal-footer">
                        <button type="button" class="btn btn-light-custom" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Save Department</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
    <script src="{{ asset('assets/js/vendor/jquery.min.js') }}"></script>
    <script src="{{ asset('assets/js/vendor/dataTables.min.js') }}"></script>
    <script src="{{ asset('assets/js/vendor/dataTables.bootstrap5.min.js') }}"></script>
    <script src="{{ asset('assets/js/vendor/dataTables.responsive.min.js') }}"></script>
    <script src="{{ asset('assets/js/vendor/responsive.bootstrap5.min.js') }}"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            var employeeIndexUrl = @json(route('admin.employees.index'));
            var activeTab = @json($activeTab);
            var departmentStoreUrl = @json(route('admin.departments.store'));
            var $ = window.jQuery;

            document.querySelectorAll('#employeeTabs [data-tab]').forEach(function (button) {
                button.addEventListener('click', function () {
                    var url = new URL(employeeIndexUrl, window.location.origin);

                    if (button.dataset.tab === 'departments') {
                        url.searchParams.set('tab', 'departments');
                    }

                    window.location.href = url.toString();
                });
            });

            if ($ && document.getElementById('employeeTable')) {
                var employeeTable = $('#employeeTable').DataTable({
                    pageLength: 10,
                    order: [[1, 'asc']],
                    responsive: true,
                    columnDefs: [
                        { orderable: false, targets: [10] }
                    ],
                    language: {
                        search: '',
                        searchPlaceholder: 'Search employees'
                    }
                });

                $.fn.dataTable.ext.search.push(function (settings, data, dataIndex) {
                    if (settings.nTable.id !== 'employeeTable') {
                        return true;
                    }

                    var row = employeeTable.row(dataIndex).node();

                    if (!row) {
                        return true;
                    }

                    var deptValue = document.getElementById('filterDept').value;
                    var statusValue = document.getElementById('filterStatus').value;
                    var locationValue = document.getElementById('filterLocation').value;
                    var typeValue = document.getElementById('filterType').value;
                    var sponsoredOnly = document.getElementById('filterSponsored').checked;

                    if (deptValue !== 'all' && row.dataset.departmentId !== deptValue) {
                        return false;
                    }

                    if (statusValue !== 'all' && row.dataset.status !== statusValue) {
                        return false;
                    }

                    if (locationValue !== 'all' && row.dataset.location !== locationValue) {
                        return false;
                    }

                    if (typeValue !== 'all' && row.dataset.type !== typeValue) {
                        return false;
                    }

                    if (sponsoredOnly && row.dataset.sponsored !== '1') {
                        return false;
                    }

                    return true;
                });

                document.getElementById('empSearch').addEventListener('input', function (event) {
                    employeeTable.search(event.target.value).draw();
                });

                ['filterDept', 'filterStatus', 'filterLocation', 'filterType', 'filterSponsored'].forEach(function (id) {
                    var field = document.getElementById(id);

                    if (!field) {
                        return;
                    }

                    field.addEventListener('change', function () {
                        employeeTable.draw();
                    });
                });

                document.querySelector('#employeeTable tbody')?.addEventListener('click', function (event) {
                    if (event.target.closest('a, button')) {
                        return;
                    }

                    var row = event.target.closest('tr');

                    if (row && row.dataset.href) {
                        window.location.href = row.dataset.href;
                    }
                });

                document.getElementById('exportEmployeesBtn')?.addEventListener('click', function () {
                    var headers = ['Employee ID', 'Employee', 'Email', 'Job Title', 'Department', 'Manager', 'Employment Type', 'Work Location', 'Start Date', 'Status', 'Right-to-Work'];
                    var rows = employeeTable.rows({ search: 'applied' }).nodes().toArray().map(function (row) {
                        var cells = row.querySelectorAll('td');

                        return [
                            cells[0]?.innerText.trim() ?? '',
                            cells[1]?.querySelector('.cell-primary')?.innerText.trim() ?? '',
                            cells[1]?.querySelector('.cell-secondary')?.innerText.trim() ?? '',
                            cells[2]?.innerText.trim() ?? '',
                            cells[3]?.innerText.trim() ?? '',
                            cells[4]?.innerText.trim() ?? '',
                            cells[5]?.innerText.trim() ?? '',
                            cells[6]?.innerText.trim() ?? '',
                            cells[7]?.innerText.trim() ?? '',
                            cells[8]?.innerText.trim() ?? '',
                            cells[9]?.innerText.trim() ?? ''
                        ];
                    });

                    var csv = [headers].concat(rows).map(function (columns) {
                        return columns.map(function (value) {
                            return '"' + String(value).replace(/"/g, '""') + '"';
                        }).join(',');
                    }).join('\n');

                    var blob = new Blob([csv], { type: 'text/csv;charset=utf-8;' });
                    var link = document.createElement('a');

                    link.href = URL.createObjectURL(blob);
                    link.download = 'employee-directory.csv';
                    link.click();
                    URL.revokeObjectURL(link.href);
                });
            }

            if ($ && document.getElementById('departmentTable')) {
                $('#departmentTable').DataTable({
                    paging: false,
                    searching: false,
                    info: false,
                    responsive: true,
                    order: [[0, 'asc']],
                    columnDefs: [
                        { orderable: false, targets: [3] }
                    ]
                });
            }

            var deptModal = document.getElementById('deptModal');

            deptModal?.addEventListener('show.bs.modal', function (event) {
                var trigger = event.relatedTarget;
                var form = document.getElementById('deptForm');
                var methodField = document.getElementById('deptFormMethod');
                var title = deptModal.querySelector('.modal-title');
                var nameField = document.getElementById('deptName');
                var statusField = document.getElementById('deptStatus');
                var isEdit = trigger && trigger.dataset.deptAction === 'edit';

                form.action = isEdit ? trigger.dataset.deptUpdateUrl : departmentStoreUrl;
                methodField.value = isEdit ? 'PUT' : '';
                title.textContent = isEdit ? 'Edit Department' : 'Add Department';
                nameField.value = isEdit ? trigger.dataset.deptName : '';
                statusField.value = isEdit ? trigger.dataset.deptStatus : 'Active';
            });

            if (activeTab === 'departments') {
                document.getElementById('tab-directory')?.classList.add('d-none');
                document.getElementById('tab-departments')?.classList.remove('d-none');
            }
        });
    </script>
@endpush
