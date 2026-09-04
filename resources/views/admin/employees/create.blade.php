@extends('layouts.master')

@section('title', 'Add Employee | Bloxt HR')
@section('meta_description', 'Create an employee record and login for Bloxt HR.')

@section('content')
    <div class="page-header-bar">
        <div class="breadcrumb-trail"><a href="{{ route('admin.employees.index') }}">Employees</a><span class="breadcrumb-sep">/</span>Add Employee</div>
        <h1 class="page-title">Add Employee</h1>
        <p class="page-subtitle">Create the employee record and their dashboard login in one step.</p>
    </div>
    <div class="app-content">@include('admin.employees._form')</div>
@endsection
