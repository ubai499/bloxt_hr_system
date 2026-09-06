@extends('layouts.master')

@section('title', 'Departments | Bloxt HR')
@section('meta_description', 'Department directory for Bloxt HR.')

@section('content')
    <div class="page-header-bar">
        <div class="d-flex justify-content-between align-items-start flex-wrap gap-3">
            <div>
                <div class="breadcrumb-trail"><a href="{{ route('admin.dashboard') }}">Dashboard</a><span class="breadcrumb-sep">/</span>Departments</div>
                <h1 class="page-title">Departments</h1>
                <p class="page-subtitle">Manage the teams used across employee records.</p>
            </div>
            <a href="{{ route('admin.departments.create') }}" class="btn btn-primary"><i class="bi bi-plus-lg"></i> Add Department</a>
        </div>
    </div>

    <div class="app-content">

        <section class="table-panel">
            <form class="table-toolbar" method="GET" action="{{ route('admin.departments.index') }}">
                <div class="table-toolbar-search">
                    <i class="bi bi-search"></i>
                    <input type="search" class="form-control" name="search" value="{{ request('search') }}" placeholder="Search departments">
                </div>
                <div class="table-toolbar-filters">
                    <select class="form-select form-select-sm" name="status" onchange="this.form.submit()">
                        <option value="">All statuses</option>
                        @foreach (['Active', 'Inactive'] as $status)
                            <option value="{{ $status }}" @selected(request('status') === $status)>{{ $status }}</option>
                        @endforeach
                    </select>
                    <button class="btn btn-sm btn-light-custom" type="submit">Filter</button>
                    @if (request()->hasAny(['search', 'status']))
                        <a class="btn btn-sm btn-ghost" href="{{ route('admin.departments.index') }}">Clear</a>
                    @endif
                </div>
            </form>

            <div class="table-responsive">
                <table class="table-app">
                    <thead><tr><th>Department</th><th>Headcount</th><th>Status</th><th class="text-end">Actions</th></tr></thead>
                    <tbody>
                        @forelse ($departments as $department)
                            <tr>
                                <td class="cell-primary">{{ $department->name }}</td>
                                <td>{{ $department->employees_count }}</td>
                                <td><span class="status-badge {{ $department->status === 'Active' ? 'badge-success' : 'badge-warning' }}">{{ $department->status }}</span></td>
                                <td class="text-end text-nowrap"><a href="{{ route('admin.departments.show', $department) }}" class="btn btn-sm btn-light-custom">View</a><a href="{{ route('admin.departments.edit', $department) }}" class="btn btn-sm btn-light-custom" title="Edit department"><i class="bi bi-pencil"></i></a><form method="POST" action="{{ route('admin.departments.destroy', $department) }}" class="d-inline" onsubmit="return confirm('Delete {{ addslashes($department->name) }}? Employees must be reassigned first.')">@csrf @method('DELETE')<button class="btn btn-sm btn-outline-danger" type="submit" title="Delete department"><i class="bi bi-trash"></i></button></form></td>
                            </tr>
                        @empty
                            <tr><td colspan="4" class="text-center py-5 text-meta"><i class="bi bi-diagram-3 d-block fs-3 mb-2"></i>No departments found. Add a department before creating employee records.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>

        @if ($departments->hasPages())
            <div class="mt-4">{{ $departments->links() }}</div>
        @endif
    </div>
@endsection
