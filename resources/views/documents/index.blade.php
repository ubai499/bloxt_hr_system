@extends('layouts.master')
@section('title', 'Documents Bloxt People & Compliance')
@section('meta_description', 'Central document library for Bloxt.')
@push('vendor-styles')
<link rel="stylesheet" href="{{ asset('assets/css/vendor/dataTables.bootstrap5.min.css') }}">
@endpush
@push('styles')
<style>
#documentsTable .document-download { color: inherit; text-decoration: none; }
#documentsTable .document-download:hover { text-decoration: underline; }
#documentsTable td { overflow-wrap: anywhere; }
</style>
@endpush
@section('content')
      <div class="page-header-bar">
        <div class="d-flex justify-content-between align-items-start flex-wrap gap-3">
          <div>
            <h1 class="page-title">Documents</h1>
            <p class="page-subtitle">Central library of employee and company documents, with expiry and retention tracking.</p>
          </div>
          <button class="btn btn-primary" id="newDocBtn"><i class="bi bi-cloud-upload"></i> Upload Document</button>
        </div>
      </div>
      <div class="app-content">
        <div class="kpi-grid mb-4" id="docKpiRow"></div>
        <div id="docPageError" class="alert alert-danger d-none" role="alert"></div>

        <div class="table-panel">
          <div class="table-toolbar">
            <div class="table-toolbar-search"><i class="bi bi-search"></i><input type="search" class="form-control" id="docSearch" aria-label="Search documents" maxlength="255" placeholder="Search document title…"></div>
            <div class="table-toolbar-filters">
              <select class="form-select form-select-sm" id="docCategoryFilter" aria-label="Filter by category" style="width:auto;"><option value="all">All categories</option>@foreach ($categories as $category)<option value="{{ $category }}">{{ $category }}</option>@endforeach</select>
              <select class="form-select form-select-sm" id="docStatusFilter" aria-label="Filter by status" style="width:auto;"><option value="all">All statuses</option>@foreach ($statuses as $status)<option>{{ $status }}</option>@endforeach</select>
              <select @class(['form-select form-select-sm', 'd-none' => $selfService]) id="docEmployeeFilter" aria-label="Filter by employee" style="width:auto;"><option value="all">All employees</option>@foreach ($employees as $employee)<option value="{{ $employee->id }}">{{ $employee->name }}</option>@endforeach</select>
            </div>
            <div class="table-toolbar-actions"><button class="btn btn-sm btn-light-custom" id="exportDocBtn"><i class="bi bi-download"></i> Export</button></div>
          </div>
          <table class="table-app" id="documentsTable" style="width:100%;">
            <thead><tr><th>Document</th><th>Category</th><th>Employee</th><th>Issue Date</th><th>Expiry Date</th><th>Status</th><th>Classification</th><th>Uploaded By</th></tr></thead>
            <tbody></tbody>
          </table>
        </div>
      </div>
@include('documents._upload_modal')
@endsection
@push('scripts')
<script src="{{ asset('assets/js/vendor/jquery.min.js') }}"></script>
<script src="{{ asset('assets/js/vendor/dataTables.min.js') }}"></script>
<script src="{{ asset('assets/js/vendor/dataTables.bootstrap5.min.js') }}"></script>
<script src="{{ asset('assets/js/app.js') }}"></script>
<script>
window.documentPage = {{ Illuminate\Support\Js::from([
    'rows' => $rows, 'filters' => $filters, 'defaultCategory' => $defaultCategory === 'all' ? 'Identity' : $defaultCategory, 'selfService' => $selfService, 'today' => today()->toDateString(),
    'indexUrl' => route($routePrefix.'.index'), 'exportUrl' => route($routePrefix.'.export'),
    'errors' => $errors->toArray(), 'old' => old(), 'openNew' => request('new') === '1' || $errors->any(),
]) }};
</script>
<script src="{{ asset('assets/js/document-library.js') }}"></script>
@endpush
