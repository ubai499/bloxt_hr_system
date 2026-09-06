@extends('layouts.master')
@section('title', 'Attendance Bloxt People & Compliance')
@section('meta_description', 'Attendance and absence records for Bloxt.')
@push('vendor-styles')
<link rel="stylesheet" href="{{ asset('assets/css/vendor/dataTables.bootstrap5.min.css') }}">
@endpush
@push('styles')
<style>
.absence-record-trigger { font: inherit; color: inherit; }
.absence-record-trigger:focus-visible { outline: 2px solid #6B6B24; outline-offset: 3px; }
.record-highlight > td { background: #F3F3E2 !important; }
</style>
@endpush
@section('content')
      <div class="page-header-bar">
        <div class="d-flex justify-content-between align-items-start flex-wrap gap-3">
          <div>
            <h1 class="page-title">Attendance</h1>
            <p class="page-subtitle">Daily attendance records and absence management.</p>
          </div>
          <div class="d-flex gap-2">
            <button class="btn btn-primary" id="recordAttendanceBtn"><i class="bi bi-calendar-check"></i> Record Attendance</button>
          </div>
        </div>
        <ul class="nav profile-tabs mt-4" id="attTabs" role="tablist" aria-label="Attendance workspace">
          <li class="nav-item"><button class="nav-link active" type="button" data-tab="attendance" id="attendanceTab" role="tab" aria-controls="tab-attendance" aria-selected="true">Daily Attendance</button></li>
          <li class="nav-item"><button class="nav-link" type="button" data-tab="absence" id="absenceTab" role="tab" aria-controls="tab-absence" aria-selected="false">Absence</button></li>
        </ul>
      </div>

      <div class="app-content">
<div id="attendancePageError" class="alert alert-danger d-none" role="alert"></div>
        <section id="tab-attendance" role="tabpanel" aria-labelledby="attendanceTab">
          <div class="table-panel">
            <div class="table-toolbar">
              <div class="table-toolbar-filters">
                <input type="date" class="form-control form-control-sm" id="attDateFilter" aria-label="Filter attendance by date" style="width:auto;">
                <select class="form-select form-select-sm" id="attStatusFilter" aria-label="Filter attendance by status" style="width:auto;"><option value="">All statuses</option>@foreach ($statuses as $status)<option>{{ $status }}</option>@endforeach</select>
                <select class="form-select form-select-sm" id="attEmployeeFilter" aria-label="Filter attendance by employee" style="width:auto;"><option value="">All employees</option>@foreach ($filterEmployees as $employee)<option value="{{ $employee->id }}">{{ $employee->name }}</option>@endforeach</select>
              </div>
              <div class="table-toolbar-actions">
                <button class="btn btn-sm btn-light-custom" id="exportAttBtn"><i class="bi bi-download"></i> Export</button>
              </div>
            </div>
            <table class="table-app" id="attendanceTable" style="width:100%;">
              <thead><tr><th>Employee</th><th>Date</th><th>Expected Start</th><th>Clock In</th><th>Clock Out</th><th>Hours</th><th>Location</th><th>Status</th><th>Notes</th><th>Reviewed</th></tr></thead>
              <tbody></tbody>
            </table>
          </div>
        </section>

        <section id="tab-absence" role="tabpanel" aria-labelledby="absenceTab" class="d-none">
          <div class="panel mb-4">
            <div class="panel-header"><div><div class="panel-title">Review Alerts</div><div class="panel-desc">Automatically generated from attendance patterns these require review, not automatic conclusions of misconduct.</div></div></div>
            <div id="alertsList" aria-live="polite"></div>
          </div>
          <div class="table-panel">
            <div class="table-toolbar">
              <div><span class="fw-semibold">Absence records</span></div>
              <div class="table-toolbar-actions">
                <button class="btn btn-sm btn-primary" id="recordAbsenceBtn"><i class="bi bi-plus-lg"></i> Record Absence</button>
              </div>
            </div>
            <table class="table-app" id="absenceTable" style="width:100%;">
              <thead><tr><th>Employee</th><th>Date</th><th>Type</th><th>Reason</th><th>Reported</th><th>Expected Return</th><th>Authorised</th><th>Follow-up</th></tr></thead>
              <tbody></tbody>
            </table>
          </div>
        </section>
      </div>
@include('admin.attendance._record_modals')
@endsection
@push('scripts')
<script src="{{ asset('assets/js/vendor/jquery.min.js') }}"></script>
<script src="{{ asset('assets/js/vendor/dataTables.min.js') }}"></script>
<script src="{{ asset('assets/js/vendor/dataTables.bootstrap5.min.js') }}"></script>
<script src="{{ asset('assets/js/app.js') }}"></script>
<script>
window.attendancePage = {{ Illuminate\Support\Js::from([
    'initial' => $payload, 'indexUrl' => route('admin.attendance.index'),
    'exportUrl' => route('admin.attendance.export'), 'today' => today()->toDateString(),
    'activeTab' => $activeTab, 'errors' => $errors->toArray(), 'old' => old(),
]) }};
</script>
<script src="{{ asset('assets/js/admin-attendance.js') }}"></script>
@endpush
