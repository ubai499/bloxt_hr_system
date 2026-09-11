@extends('layouts.master')
@section('title', 'Audit Log Bloxt People & Compliance')
@section('meta_description', 'Global audit trail for Bloxt.')

@push('vendor-styles')
    <link rel="stylesheet" href="{{ asset('assets/css/vendor/dataTables.bootstrap5.min.css') }}">
@endpush

@section('content')
    <div class="page-header-bar">
        <h1 class="page-title">Audit Log</h1>
        <p class="page-subtitle">Immutable record of significant actions taken across the system. Only visible to authorised roles.</p>
    </div>
    <div class="app-content">
        <div id="auditPageError" class="alert alert-danger d-none" role="alert"></div>
        <div class="table-panel">
            <div class="table-toolbar">
                <div class="table-toolbar-search"><i class="bi bi-search"></i><input type="search" class="form-control" id="auditSearch" aria-label="Search action or description" maxlength="255" placeholder="Search action or description…"></div>
                <div class="table-toolbar-filters">
                    <select class="form-select form-select-sm" id="auditModuleFilter" aria-label="Filter by module" style="width:auto;">
                        <option value="all">All modules</option>
                        @foreach ($modules as $module)<option @selected($filters['module'] === $module)>{{ $module }}</option>@endforeach
                    </select>
                    <input type="date" class="form-control form-control-sm" id="auditDateFilter" aria-label="Filter by date" style="width:auto;" value="{{ $filters['date'] }}">
                </div>
                <div class="table-toolbar-actions"><button type="button" class="btn btn-sm btn-light-custom" id="exportAuditBtn"><i class="bi bi-download"></i> Export</button></div>
            </div>
            <table class="table-app" id="auditTable" style="width:100%;">
                <thead><tr><th>Timestamp</th><th>User</th><th>Action</th><th>Module</th><th>Employee</th><th>Description</th><th>Previous</th><th>New</th></tr></thead>
                <tbody></tbody>
            </table>
        </div>
    </div>
@endsection

@push('scripts')
    <script src="{{ asset('assets/js/vendor/jquery.min.js') }}"></script>
    <script src="{{ asset('assets/js/vendor/dataTables.min.js') }}"></script>
    <script src="{{ asset('assets/js/vendor/dataTables.bootstrap5.min.js') }}"></script>
    <script src="{{ asset('assets/js/app.js') }}"></script>
    <script>
        window.auditPage = {{ Illuminate\Support\Js::from([
            'initial' => $payload,
            'filters' => $filters,
            'indexUrl' => route('admin.audit-log.index'),
            'exportUrl' => route('admin.audit-log.export'),
            'highlight' => request('highlight'),
        ]) }};
    </script>
    <script src="{{ asset('assets/js/admin-audit.js') }}"></script>
@endpush
