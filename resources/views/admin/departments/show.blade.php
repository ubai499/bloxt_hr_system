@extends('layouts.master')

@section('title', $department->name . ' | Bloxt HR')
@section('meta_description', 'Department details for Bloxt HR.')

@section('content')
    <div class="page-header-bar">
        <div class="breadcrumb-trail"><a href="{{ route('admin.departments.index') }}">Departments</a><span class="breadcrumb-sep">/</span>{{ $department->name }}</div>
    </div>

    <div class="app-content">
        @if (session('success'))
            <div class="alert alert-success" role="alert">{{ session('success') }}</div>
        @endif
        @if (session('error'))
            <div class="alert alert-danger" role="alert">{{ session('error') }}</div>
        @endif

        <section class="profile-header">
            <span class="avatar-circle avatar-lg"><i class="bi bi-diagram-3"></i></span>
            <div class="profile-header-info">
                <h1 class="profile-name">{{ $department->name }}</h1>
                <div class="profile-role">Department</div>
                <div class="profile-meta-row">
                    <span><i class="bi bi-people"></i>{{ $employees->total() }} employee{{ $employees->total() === 1 ? '' : 's' }}</span>
                    <span><i class="bi bi-check-circle"></i>{{ $department->status }}</span>
                </div>
            </div>
            <div class="profile-header-actions">
                <a href="{{ route('admin.departments.edit', $department) }}" class="btn btn-primary"><i class="bi bi-pencil"></i> Edit Department</a>
            </div>
        </section>

        <section class="table-panel">
            <div class="table-toolbar"><div><span class="fw-semibold">Employees in this department</span></div><div class="table-toolbar-actions"><a href="{{ route('admin.employees.create') }}" class="btn btn-sm btn-light-custom"><i class="bi bi-person-plus"></i> Add Employee</a></div></div>
            <div class="table-responsive">
                <table class="table-app">
                    <thead><tr><th>Employee</th><th>Job Title</th><th>Email</th><th>Status</th><th class="text-end">Actions</th></tr></thead>
                    <tbody>
                        @forelse ($employees as $employee)
                            <tr>
                                <td class="cell-primary">{{ $employee->name }}</td>
                                <td>{{ $employee->job_title }}</td>
                                <td>{{ $employee->email }}</td>
                                <td><span class="status-badge {{ $employee->status === 'Active' ? 'badge-success' : 'badge-warning' }}">{{ $employee->status }}</span></td>
                                <td class="text-end text-nowrap"><a href="{{ route('admin.employees.show', $employee) }}" class="btn btn-sm btn-light-custom">View</a><a href="{{ route('admin.employees.edit', $employee) }}" class="btn btn-sm btn-light-custom" title="Edit employee"><i class="bi bi-pencil"></i></a><form method="POST" action="{{ route('admin.employees.destroy', $employee) }}" class="d-inline" onsubmit="return confirm('Delete {{ addslashes($employee->name) }} and their login account?')">@csrf @method('DELETE')<button class="btn btn-sm btn-outline-danger" type="submit" title="Delete employee"><i class="bi bi-trash"></i></button></form></td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="text-center py-5 text-meta">No employees are assigned to this department.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>

        @if ($employees->hasPages())
            <div class="mt-4">{{ $employees->links() }}</div>
        @endif

        @if ($employees->isEmpty())
            <form method="POST" action="{{ route('admin.departments.destroy', $department) }}" class="mt-4" onsubmit="return confirm('Delete {{ addslashes($department->name) }}?')">
                @csrf
                @method('DELETE')
                <button class="btn btn-outline-danger" type="submit"><i class="bi bi-trash"></i> Delete Department</button>
            </form>
        @endif
    </div>
@endsection
