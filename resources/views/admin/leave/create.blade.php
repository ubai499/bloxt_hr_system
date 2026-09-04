@extends('layouts.master')
@section('title', 'Request Leave | Bloxt HR')
@section('content')
    <div class="page-header-bar"><div class="breadcrumb-trail"><a href="{{ route('admin.leave.index') }}">Leave</a><span class="breadcrumb-sep">/</span>Request Leave</div><h1 class="page-title">Request Leave</h1><p class="page-subtitle">Submit a leave request for an employee.</p></div>
    <div class="app-content">@include('admin.leave._form')</div>
@endsection
