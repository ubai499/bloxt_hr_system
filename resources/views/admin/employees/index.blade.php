@extends('layouts.master')

@section('title', 'Employees | Bloxt HR')
@section('meta_description', 'Employee directory for Bloxt HR.')

@section('content')
    <div class="page-header-bar">
        <div class="d-flex justify-content-between align-items-start flex-wrap gap-3">
            <div>
                <div class="breadcrumb-trail"><a href="{{ route('admin.dashboard') }}">Dashboard</a><span class="breadcrumb-sep">/</span>Employees</div>
                <h1 class="page-title">Employees</h1>
                <p class="page-subtitle">Directory, employment details, and login access.</p>
            </div>
            <a href="{{ route('admin.employees.create') }}" class="btn btn-primary"><i class="bi bi-person-plus"></i> Add Employee</a>
        </div>
    </div>

    <div class="app-content">
        @if (session('success'))
            <div class="alert alert-success" role="alert">{{ session('success') }}</div>
        @endif

        <section class="table-panel">
            <form class="table-toolbar" method="GET" action="{{ route('admin.employees.index') }}">
                <div class="table-toolbar-search">
                    <i class="bi bi-search"></i>
                    <input type="search" class="form-control" name="search" value="{{ request('search') }}" placeholder="Search name, ID, email, or job title">
                </div>
                <div class="table-toolbar-filters">
                    <select class="form-select form-select-sm" name="department" onchange="this.form.submit()">
                        <option value="">All departments</option>
                        @foreach ($departments as $department)
                            <option value="{{ $department->id }}" @selected((string) request('department') === (string) $department->id)>{{ $department->name }}</option>
                        @endforeach
                    </select>
                    <select class="form-select form-select-sm" name="status" onchange="this.form.submit()">
                        <option value="">All statuses</option>
                        @foreach (['Active', 'On Leave', 'Probation', 'Left'] as $status)
                            <option value="{{ $status }}" @selected(request('status') === $status)>{{ $status }}</option>
                        @endforeach
                    </select>
                    <button class="btn btn-sm btn-light-custom" type="submit">Filter</button>
                    @if (request()->hasAny(['search', 'department', 'status']))
                        <a class="btn btn-sm btn-ghost" href="{{ route('admin.employees.index') }}">Clear</a>
                    @endif
                </div>
            </form>

            <div class="table-responsive">
                <table class="table-app">
                    <thead>
                        <tr><th>Employee</th><th>Job Title</th><th>Department</th><th>Manager</th><th>Type</th><th>Start Date</th><th>Status</th><th></th></tr>
                    </thead>
                    <tbody>
                        @forelse ($employees as $employee)
                            <tr>
                                <td>
                                    <div class="employee-cell">
                                        <span class="avatar-circle">{{ collect(explode(' ', $employee->name))->filter()->take(2)->map(fn ($part) => strtoupper(substr($part, 0, 1)))->implode('') }}</span>
                                        <div><div class="cell-primary">{{ $employee->name }}</div><div class="cell-secondary">{{ $employee->employee_number ?: $employee->email }}</div></div>
                                    </div>
                                </td>
                                <td>{{ $employee->job_title }}</td>
                                <td>{{ $employee->departmentRecord?->name ?: '-' }}</td>
                                <td>{{ $employee->manager?->name ?: '-' }}</td>
                                <td>{{ $employee->employment_type }}</td>
                                <td>{{ $employee->start_date?->format('d M Y') ?: '-' }}</td>
                                <td><span class="status-badge {{ $employee->status === 'Active' ? 'badge-success' : ($employee->status === 'Left' ? 'badge-danger' : 'badge-warning') }}">{{ $employee->status }}</span></td>
                                <td class="text-end"><a href="{{ route('admin.employees.show', $employee) }}" class="btn btn-sm btn-light-custom">View</a></td>
                            </tr>
                        @empty
                            <tr><td colspan="8" class="text-center py-5 text-meta"><i class="bi bi-people d-block fs-3 mb-2"></i>No employees found. Add the first employee to create their HR record and login.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>

        @if ($employees->hasPages())
            <div class="mt-4">{{ $employees->links() }}</div>
        @endif
    </div>
@endsection
