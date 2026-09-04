@extends('layouts.master')
@section('title', 'Edit Attendance | Bloxt HR')
@section('content')
    <div class="page-header-bar"><div class="breadcrumb-trail"><a href="{{ route('admin.attendance.index') }}">Attendance</a><span class="breadcrumb-sep">/</span>Edit Record</div><h1 class="page-title">Edit Attendance</h1><p class="page-subtitle">Update this attendance record or its review status.</p></div>
    <div class="app-content">@include('admin.attendance._form')<form method="POST" action="{{ route('admin.attendance.destroy', $record) }}" onsubmit="return confirm('Delete this attendance record?')">@csrf @method('DELETE')<button class="btn btn-outline-danger mb-5" type="submit"><i class="bi bi-trash"></i> Delete Record</button></form></div>
@endsection
