@extends('layouts.master')

@php
    $statusClass = fn ($status) => match ($status) {
        'Approved' => 'badge-success',
        'Rejected', 'Cancelled' => 'badge-danger',
        default => 'badge-warning',
    };
@endphp

@section('title', 'Leave | Bloxt HR')
@section('meta_description', 'Leave requests and approvals for Bloxt HR.')

@section('content')
    <div class="page-header-bar">
        <div class="d-flex justify-content-between align-items-start flex-wrap gap-3">
            <div>
                <div class="breadcrumb-trail"><a href="{{ route('admin.dashboard') }}">Dashboard</a><span class="breadcrumb-sep">/</span>Leave</div>
                <h1 class="page-title">Leave</h1>
                <p class="page-subtitle">Leave requests, approvals and balances.</p>
            </div>
            <a href="{{ route('admin.leave.create') }}" class="btn btn-primary"><i class="bi bi-plus-lg"></i> Request Leave</a>
        </div>
    </div>

    <div class="app-content">
        @if (session('success'))<div class="alert alert-success" role="alert">{{ session('success') }}</div>@endif

        <div class="kpi-grid mb-4">
            <div class="metric-card metric-accent-warning"><span class="metric-icon"><i class="bi bi-hourglass-split"></i></span><span class="metric-label">Pending Requests</span><span class="metric-value">{{ $stats['pending'] }}</span><span class="metric-context">Awaiting a decision</span></div>
            <div class="metric-card metric-accent-success"><span class="metric-icon"><i class="bi bi-check2-circle"></i></span><span class="metric-label">Approved Requests</span><span class="metric-value">{{ $stats['approved'] }}</span><span class="metric-context">Recorded requests</span></div>
            <div class="metric-card metric-accent-info"><span class="metric-icon"><i class="bi bi-airplane"></i></span><span class="metric-label">On Leave Today</span><span class="metric-value">{{ $stats['on_leave_today'] }}</span><span class="metric-context">Approved leave today</span></div>
            <div class="metric-card metric-accent-primary"><span class="metric-icon"><i class="bi bi-list-check"></i></span><span class="metric-label">Total Requests</span><span class="metric-value">{{ $stats['total'] }}</span><span class="metric-context">All leave records</span></div>
        </div>

        <section class="table-panel">
            <form class="table-toolbar" method="GET" action="{{ route('admin.leave.index') }}">
                <div class="table-toolbar-filters">
                    <select class="form-select form-select-sm" name="status" onchange="this.form.submit()" style="width:auto;"><option value="">All statuses</option>@foreach ($leaveStatuses as $status)<option value="{{ $status }}" @selected(request('status') === $status)>{{ $status }}</option>@endforeach</select>
                    <select class="form-select form-select-sm" name="type" onchange="this.form.submit()" style="width:auto;"><option value="">All leave types</option>@foreach ($leaveTypes as $type)<option value="{{ $type }}" @selected(request('type') === $type)>{{ $type }}</option>@endforeach</select>
                    <select class="form-select form-select-sm" name="employee" onchange="this.form.submit()" style="width:auto;"><option value="">All employees</option>@foreach ($employees as $employee)<option value="{{ $employee->id }}" @selected((string) request('employee') === (string) $employee->id)>{{ $employee->name }}</option>@endforeach</select>
                    <button class="btn btn-sm btn-light-custom" type="submit">Filter</button>
                    @if (request()->hasAny(['status', 'type', 'employee']))<a class="btn btn-sm btn-ghost" href="{{ route('admin.leave.index') }}">Clear</a>@endif
                </div>
            </form>
            <div class="table-responsive"><table class="table-app"><thead><tr><th>Employee</th><th>Type</th><th>From</th><th>To</th><th>Reason</th><th>Status</th><th>Approved By</th><th></th></tr></thead><tbody>
                @forelse ($leaveRequests as $leaveRequest)
                    <tr><td class="cell-primary">{{ $leaveRequest->employee->name }}</td><td>{{ $leaveRequest->leave_type }}{{ $leaveRequest->partial_day ? ' (Partial day)' : '' }}</td><td>{{ $leaveRequest->from_date->format('d M Y') }}</td><td>{{ $leaveRequest->to_date->format('d M Y') }}</td><td class="cell-secondary">{{ $leaveRequest->reason ?: '-' }}</td><td><span class="status-badge {{ $statusClass($leaveRequest->status) }}">{{ $leaveRequest->status }}</span></td><td>{{ $leaveRequest->approved_by ?: '-' }}</td><td class="text-end">
                        @if ($leaveRequest->status === 'Pending')
                            <form method="POST" action="{{ route('admin.leave.status.update', $leaveRequest) }}" class="d-inline">@csrf @method('PATCH')<input type="hidden" name="status" value="Approved"><button class="btn btn-sm btn-outline-primary" type="submit">Approve</button></form>
                            <form method="POST" action="{{ route('admin.leave.status.update', $leaveRequest) }}" class="d-inline">@csrf @method('PATCH')<input type="hidden" name="status" value="Rejected"><button class="btn btn-sm btn-light-custom" type="submit">Reject</button></form>
                        @endif
                        <a href="{{ route('admin.leave.edit', $leaveRequest) }}" class="btn btn-sm btn-light-custom ms-1">Edit</a>
                    </td></tr>
                @empty
                    <tr><td colspan="8" class="text-center py-5 text-meta"><i class="bi bi-airplane d-block fs-3 mb-2"></i>No leave requests found. Create a request to begin the approval workflow.</td></tr>
                @endforelse
            </tbody></table></div>
        </section>
        @if ($leaveRequests->hasPages())<div class="mt-4">{{ $leaveRequests->links() }}</div>@endif
    </div>
@endsection
