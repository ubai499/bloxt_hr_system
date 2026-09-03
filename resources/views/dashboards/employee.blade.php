@extends('layouts.master')

@section('title', 'My Dashboard | Bloxt HR')
@section('meta_description', 'Employee self-service dashboard for Bloxt People and Compliance.')

@section('content')
    @php $firstName = strtok(auth()->user()->name ?? 'there', ' '); @endphp
    <div class="page-header-bar">
        <div class="greeting-header">
            <div>
                <div class="eyebrow-label">Employee workspace</div>
                <h1 class="page-title">Good day, {{ $firstName }}</h1>
                <p class="page-subtitle">Your work, time off and required actions in one place.</p>
            </div>
            <span class="text-meta">{{ now()->format('D, d M Y') }}</span>
        </div>
    </div>

    <div class="app-content">
        <div class="kpi-grid mb-5">
            <div class="metric-card metric-accent-primary"><span class="metric-icon"><i class="bi bi-airplane"></i></span><span class="metric-label">Annual Leave Remaining</span><span class="metric-value">18</span><span class="metric-context">days available</span></div>
            <div class="metric-card metric-accent-success"><span class="metric-icon"><i class="bi bi-calendar-check"></i></span><span class="metric-label">Next Payday</span><span class="metric-value">27</span><span class="metric-context">September 2026</span></div>
            <div class="metric-card metric-accent-warning"><span class="metric-icon"><i class="bi bi-list-check"></i></span><span class="metric-label">Required Actions</span><span class="metric-value">1</span><span class="metric-context">needs your attention</span></div>
            <div class="metric-card metric-accent-danger"><span class="metric-icon"><i class="bi bi-clock-history"></i></span><span class="metric-label">Hours This Week</span><span class="metric-value">37.5</span><span class="metric-context">contracted hours</span></div>
        </div>

        <div class="dashboard-columns">
            <div>
                <section class="panel mb-5">
                    <div class="panel-header"><div class="panel-title">My Actions</div><span class="panel-subtitle">Keep your record up to date</span></div>
                    <div class="action-item"><span class="action-priority-dot priority-dot-medium"></span><div class="action-body"><div class="action-title">Confirm your contact details</div><div class="action-meta">Review your emergency contact and home address.</div></div><button class="btn btn-sm btn-primary" type="button" disabled>Review</button></div>
                    <div class="action-item"><span class="action-priority-dot priority-dot-low"></span><div class="action-body"><div class="action-title">Complete security awareness training</div><div class="action-meta">Due 30 September 2026</div></div><button class="btn btn-sm btn-light-custom" type="button" disabled>View</button></div>
                </section>
                <section class="panel"><div class="panel-header"><div class="panel-title">Upcoming Time Off</div></div><div class="empty-state py-4"><div class="empty-state-icon"><i class="bi bi-airplane"></i></div><div class="empty-state-title">No upcoming leave</div><div class="empty-state-text">Approved leave will appear here.</div></div></section>
            </div>
            <div>
                <section class="panel mb-5"><div class="panel-header"><div class="panel-title">At a Glance</div></div><dl class="detail-grid"><div class="detail-item"><dt>Manager</dt><dd>HR Administrator</dd></div><div class="detail-item"><dt>Department</dt><dd>Operations</dd></div><div class="detail-item"><dt>Employment Status</dt><dd><span class="employee-status">Active</span></dd></div><div class="detail-item"><dt>System Role</dt><dd>Employee</dd></div></dl></section>
                <section class="panel"><div class="panel-header"><div class="panel-title">Company Updates</div></div><div class="timeline"><div class="timeline-item"><div class="timeline-date">Today</div><div class="timeline-title">Welcome to your Bloxt employee dashboard</div><div class="timeline-by">Your self-service workspace is ready.</div></div><div class="timeline-item"><div class="timeline-date">This week</div><div class="timeline-title">September payroll cut-off</div><div class="timeline-by">Submit approved changes before 18 September.</div></div></div></section>
            </div>
        </div>
    </div>
@endsection

@push('styles')
<style>
    .eyebrow-label { color: #6d7787; font-size: .75rem; font-weight: 700; letter-spacing: .08em; text-transform: uppercase; margin-bottom: .35rem; }
    .employee-status { background: #dcfce7; border-radius: 999px; color: #166534; display: inline-block; font-size: .75rem; font-weight: 600; padding: .25rem .55rem; }
</style>
@endpush
