@extends('layouts.master')

@section('title', 'Add Department | Bloxt HR')
@section('meta_description', 'Create a department for Bloxt HR.')

@section('content')
    <div class="page-header-bar">
        <div class="breadcrumb-trail"><a href="{{ route('admin.departments.index') }}">Departments</a><span class="breadcrumb-sep">/</span>Add Department</div>
        <h1 class="page-title">Add Department</h1>
        <p class="page-subtitle">Add a department for organising employee records.</p>
    </div>
    <div class="app-content">@include('admin.departments._form')</div>
@endsection
