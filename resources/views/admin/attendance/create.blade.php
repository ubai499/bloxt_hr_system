@extends('layouts.master')
@section('title', 'Record Attendance | Bloxt HR')
@section('content')
    <div class="page-header-bar"><div class="breadcrumb-trail"><a href="{{ route('admin.attendance.index') }}">Attendance</a><span class="breadcrumb-sep">/</span>Record Attendance</div><h1 class="page-title">Record Attendance</h1><p class="page-subtitle">Add a daily attendance entry for an employee.</p></div>
    <div class="app-content">@include('admin.attendance._form')</div>
@endsection
