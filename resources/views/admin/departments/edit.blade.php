@extends('layouts.master')

@section('title', 'Edit Department | Bloxt HR')
@section('meta_description', 'Update a department for Bloxt HR.')

@section('content')
    <div class="page-header-bar">
        <div class="breadcrumb-trail"><a href="{{ route('admin.departments.index') }}">Departments</a><span class="breadcrumb-sep">/</span><a href="{{ route('admin.departments.show', $department) }}">{{ $department->name }}</a><span class="breadcrumb-sep">/</span>Edit</div>
        <h1 class="page-title">Edit Department</h1>
        <p class="page-subtitle">Update {{ $department->name }}'s status or name.</p>
    </div>
    <div class="app-content">@include('admin.departments._form')</div>
@endsection
