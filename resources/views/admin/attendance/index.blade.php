@extends('layouts.master')

@php
    $statusClass = fn ($status) => match ($status) {
        'Present', 'Remote', 'Office', 'Business Travel', 'Training' => 'badge-success',
        'Unauthorised Absence' => 'badge-danger',
        default => 'badge-warning',
    };
@endphp

@section('title', 'Attendance | Bloxt HR')
@section('meta_description', 'Daily attendance records and absence management for Bloxt HR.')

@section('content')
    <div class="page-header-bar">
        <div class="d-flex justify-content-between align-items-start flex-wrap gap-3">
            <div>
                <div class="breadcrumb-trail"><a href="{{ route('admin.dashboard') }}">Dashboard</a><span class="breadcrumb-sep">/</span>Attendance</div>
                <h1 class="page-title">Attendance</h1>
                <p class="page-subtitle">Daily attendance records and absence management.</p>
            </div>
            <a href="{{ $activeTab === 'absence' ? route('admin.attendance.absence.create') : route('admin.attendance.create') }}" class="btn btn-primary"><i class="bi {{ $activeTab === 'absence' ? 'bi-plus-lg' : 'bi-calendar-check' }}"></i> {{ $activeTab === 'absence' ? 'Record Absence' : 'Record Attendance' }}</a>
        </div>
        <ul class="nav profile-tabs mt-4">
            <li class="nav-item"><a class="nav-link {{ $activeTab === 'attendance' ? 'active' : '' }}" href="{{ route('admin.attendance.index') }}">Daily Attendance</a></li>
            <li class="nav-item"><a class="nav-link {{ $activeTab === 'absence' ? 'active' : '' }}" href="{{ route('admin.attendance.index', ['tab' => 'absence']) }}">Absence</a></li>
        </ul>
    </div>

    <div class="app-content">
        @if (session('success'))<div class="alert alert-success" role="alert">{{ session('success') }}</div>@endif

        @if ($activeTab === 'attendance')
            <section class="table-panel">
                <form class="table-toolbar" method="GET" action="{{ route('admin.attendance.index') }}">
                    <div class="table-toolbar-filters">
                        <input type="date" class="form-control form-control-sm" name="date" value="{{ request('date') }}" style="width:auto;">
                        <select class="form-select form-select-sm" name="status" onchange="this.form.submit()" style="width:auto;"><option value="">All statuses</option>@foreach ($statuses as $status)<option value="{{ $status }}" @selected(request('status') === $status)>{{ $status }}</option>@endforeach</select>
                        <select class="form-select form-select-sm" name="employee" onchange="this.form.submit()" style="width:auto;"><option value="">All employees</option>@foreach ($employees as $employee)<option value="{{ $employee->id }}" @selected((string) request('employee') === (string) $employee->id)>{{ $employee->name }}</option>@endforeach</select>
                        <button class="btn btn-sm btn-light-custom" type="submit">Filter</button>
                        @if (request()->hasAny(['date', 'status', 'employee']))<a class="btn btn-sm btn-ghost" href="{{ route('admin.attendance.index') }}">Clear</a>@endif
                    </div>
                </form>
                <div class="table-responsive"><table class="table-app"><thead><tr><th>Employee</th><th>Date</th><th>Expected Start</th><th>Clock In</th><th>Clock Out</th><th>Hours</th><th>Location</th><th>Status</th><th>Notes</th><th>Reviewed</th></tr></thead><tbody>
                    @forelse ($attendance as $record)
                        <tr><td class="cell-primary">{{ $record->employee->name }}</td><td>{{ $record->date->format('d M Y') }}</td><td>{{ $record->expected_start ? substr($record->expected_start, 0, 5) : '-' }}</td><td>{{ $record->clock_in ? substr($record->clock_in, 0, 5) : '-' }}</td><td>{{ $record->clock_out ? substr($record->clock_out, 0, 5) : '-' }}</td><td>{{ $record->hours }}</td><td>{{ $record->work_location ?: '-' }}</td><td><span class="status-badge {{ $statusClass($record->status) }}">{{ $record->status }}</span></td><td class="cell-secondary">{{ $record->notes ?: '-' }}</td><td class="text-end">@if ($record->manager_reviewed)<i class="bi bi-check-circle text-success" title="Reviewed"></i>@else<form method="POST" action="{{ route('admin.attendance.review', $record) }}">@csrf @method('PATCH')<button class="btn btn-sm btn-outline-primary" type="submit">Mark reviewed</button></form>@endif <a href="{{ route('admin.attendance.edit', $record) }}" class="btn btn-sm btn-light-custom ms-1">Edit</a></td></tr>
                    @empty
                        <tr><td colspan="10" class="text-center py-5 text-meta"><i class="bi bi-calendar-check d-block fs-3 mb-2"></i>No attendance records match these filters. Record a new attendance entry to begin.</td></tr>
                    @endforelse
                </tbody></table></div>
            </section>
            @if ($attendance->hasPages())<div class="mt-4">{{ $attendance->links() }}</div>@endif
        @else
            <section class="panel mb-4"><div class="panel-header"><div><div class="panel-title">Review Alerts</div><div class="panel-desc">Attendance records awaiting manager confirmation require review.</div></div></div>
                @forelse ($pendingReviews as $record)
                    <div class="action-item"><span class="action-priority-dot priority-dot-medium"></span><div class="action-body"><div class="action-title">Attendance information awaiting manager confirmation</div><div class="action-meta"><span><i class="bi bi-person"></i>{{ $record->employee->name }}</span><span><i class="bi bi-calendar-event"></i>{{ $record->date->format('d M Y') }}</span></div></div><a href="{{ route('admin.attendance.edit', $record) }}" class="btn btn-sm btn-light-custom">Review</a></div>
                @empty
                    <div class="empty-state py-4"><div class="empty-state-icon"><i class="bi bi-check2-circle"></i></div><div class="empty-state-title">No review alerts</div><div class="empty-state-text">All attendance records have been reviewed.</div></div>
                @endforelse
            </section>
            <section class="table-panel"><div class="table-toolbar"><div><span class="fw-semibold">Absence records</span></div><div class="table-toolbar-actions"><a href="{{ route('admin.attendance.absence.create') }}" class="btn btn-sm btn-primary"><i class="bi bi-plus-lg"></i> Record Absence</a></div></div>
                <div class="table-responsive"><table class="table-app"><thead><tr><th>Employee</th><th>Date</th><th>Type</th><th>Reason</th><th>Reported</th><th>Expected Return</th><th>Authorised</th><th>Follow-up</th><th></th></tr></thead><tbody>
                    @forelse ($absences as $absence)
                        <tr><td class="cell-primary">{{ $absence->employee->name }}</td><td>{{ $absence->date->format('d M Y') }}</td><td><span class="status-badge {{ $absence->absence_type === 'Unauthorised Absence' ? 'badge-danger' : 'badge-warning' }}">{{ $absence->absence_type }}</span></td><td>{{ $absence->reason ?: '-' }}</td><td>{{ $absence->reported_date?->format('d M Y') ?: 'Not reported' }}{{ $absence->how_reported ? ' ('.$absence->how_reported.')' : '' }}</td><td>{{ $absence->expected_return?->format('d M Y') ?: '-' }}</td><td>{!! $absence->authorised ? '<i class="bi bi-check-circle text-success"></i> Yes' : '<i class="bi bi-x-circle text-danger"></i> No' !!}</td><td>{{ $absence->follow_up_required ? 'Required' : '-' }}</td><td class="text-end"><a href="{{ route('admin.attendance.absence.edit', $absence) }}" class="btn btn-sm btn-light-custom">Edit</a></td></tr>
                    @empty
                        <tr><td colspan="9" class="text-center py-5 text-meta"><i class="bi bi-clipboard-x d-block fs-3 mb-2"></i>No absence records have been logged.</td></tr>
                    @endforelse
                </tbody></table></div>
            </section>
            @if ($absences->hasPages())<div class="mt-4">{{ $absences->links() }}</div>@endif
        @endif
    </div>
@endsection
