@extends('layouts.master')
@section('title', 'Record Absence | Bloxt HR')
@section('content')
    <div class="page-header-bar"><div class="breadcrumb-trail"><a href="{{ route('admin.attendance.index', ['tab' => 'absence']) }}">Attendance</a><span class="breadcrumb-sep">/</span>Record Absence</div><h1 class="page-title">Record Absence</h1><p class="page-subtitle">Add an absence record and any required follow-up.</p></div>
    <div class="app-content">@include('admin.attendance._absence-form')</div>
@endsection
