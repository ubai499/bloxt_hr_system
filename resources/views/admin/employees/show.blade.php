@extends('layouts.master')

@section('title', $employee->name . ' | Bloxt HR')
@section('meta_description', 'Employee profile for Bloxt HR.')

@section('content')
    <div class="page-header-bar">
        <div class="breadcrumb-trail"><a href="{{ route('admin.employees.index') }}">Employees</a><span class="breadcrumb-sep">/</span>{{ $employee->name }}</div>
    </div>

    <div class="app-content">

        <section class="profile-header">
            <span class="avatar-circle avatar-lg">{{ collect(explode(' ', $employee->name))->filter()->take(2)->map(fn ($part) => strtoupper(substr($part, 0, 1)))->implode('') }}</span>
            <div class="profile-header-info">
                <h1 class="profile-name">{{ $employee->name }}</h1>
                <div class="profile-role">{{ $employee->job_title }}</div>
                <div class="profile-meta-row">
                    <span><i class="bi bi-building"></i>{{ $employee->departmentRecord?->name ?: 'No department' }}</span>
                    <span><i class="bi bi-envelope"></i>{{ $employee->email }}</span>
                    <span><i class="bi bi-person-badge"></i>{{ $employee->employee_number ?: 'No employee number' }}</span>
                </div>
            </div>
            <div class="profile-header-actions">
                <a href="{{ route('admin.employees.edit', $employee) }}" class="btn btn-primary"><i class="bi bi-pencil"></i> Edit Employee</a>
                <button type="button" class="btn btn-sm btn-light-custom" id="uploadDocBtn" data-bs-toggle="modal" data-bs-target="#uploadDocModal"><i class="bi bi-cloud-upload"></i> Upload Document</button>
            </div>
        </section>

        <ul class="nav profile-tabs" id="profileTabs">
            <li class="nav-item"><a class="nav-link {{ request('tab') !== 'documents' ? 'active' : '' }}" href="{{ route('admin.employees.show', $employee) }}">Overview</a></li>
            <li class="nav-item"><a class="nav-link {{ request('tab') === 'documents' ? 'active' : '' }}" href="{{ route('admin.employees.show', ['employee' => $employee, 'tab' => 'documents']) }}">Documents</a></li>
        </ul>
        @if (request('tab') === 'documents')
            @include('admin.employees._documents')
        @else
        <div class="dashboard-columns">
            <section class="panel">
                <div class="panel-header"><div class="panel-title">Employment details</div></div>
                <dl class="detail-grid">
                    <div class="detail-item"><dt>Status</dt><dd><span class="status-badge {{ $employee->status === 'Active' ? 'badge-success' : 'badge-warning' }}">{{ $employee->status }}</span></dd></div>
                    <div class="detail-item"><dt>Employment type</dt><dd>{{ $employee->employment_type }}</dd></div>
                    <div class="detail-item"><dt>Start date</dt><dd>{{ $employee->start_date?->format('d M Y') ?: '-' }}</dd></div>
                    <div class="detail-item"><dt>Work location</dt><dd>{{ $employee->work_location ?: '-' }}</dd></div>
                    <div class="detail-item"><dt>Manager</dt><dd>{{ $employee->manager?->name ?: 'Not assigned' }}</dd></div>
                    <div class="detail-item"><dt>Phone</dt><dd>{{ $employee->phone ?: '-' }}</dd></div>
                </dl>
            </section>
            <section class="panel">
                <div class="panel-header"><div class="panel-title">Contact details</div></div>
                <dl class="detail-grid" style="grid-template-columns: 1fr;">
                    <div class="detail-item"><dt>Work email</dt><dd>{{ $employee->email }}</dd></div>
                    <div class="detail-item"><dt>Home address</dt><dd>{{ $employee->address ?: 'Not recorded' }}</dd></div>
                    <div class="detail-item"><dt>Login access</dt><dd>Employee dashboard enabled</dd></div>
                </dl>
            </section>
        </div>

        <form method="POST" action="{{ route('admin.employees.destroy', $employee) }}" class="mt-4" onsubmit="return confirm('Delete {{ addslashes($employee->name) }} and their login account?')">
            @csrf
            @method('DELETE')
            <button class="btn btn-outline-danger" type="submit"><i class="bi bi-trash"></i> Delete Employee</button>
        </form>
        @endif
    </div>
    @include('admin.employees._document_upload')
@endsection
