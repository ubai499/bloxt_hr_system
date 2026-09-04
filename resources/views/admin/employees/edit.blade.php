@extends('layouts.master')

@section('title', 'Edit Employee | Bloxt HR')
@section('meta_description', 'Update an employee record for Bloxt HR.')

@section('content')
    <div class="page-header-bar">
        <div class="breadcrumb-trail"><a href="{{ route('admin.employees.index') }}">Employees</a><span class="breadcrumb-sep">/</span><a href="{{ route('admin.employees.show', $employee) }}">{{ $employee->name }}</a><span class="breadcrumb-sep">/</span>Edit</div>
        <h1 class="page-title">Edit Employee</h1>
        <p class="page-subtitle">Update {{ $employee->name }}'s employment details and access.</p>
    </div>
    <div class="app-content">@include('admin.employees._form')</div>
@endsection
