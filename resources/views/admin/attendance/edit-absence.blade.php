@extends('layouts.master')
@section('title', 'Edit Absence | Bloxt HR')
@section('content')
    <div class="page-header-bar"><div class="breadcrumb-trail"><a href="{{ route('admin.attendance.index', ['tab' => 'absence']) }}">Attendance</a><span class="breadcrumb-sep">/</span>Edit Absence</div><h1 class="page-title">Edit Absence</h1><p class="page-subtitle">Update the absence record, authorisation, and follow-up details.</p></div>
    <div class="app-content">@include('admin.attendance._absence-form')<form method="POST" action="{{ route('admin.attendance.absence.destroy', $absence) }}" onsubmit="return confirm('Delete this absence record?')">@csrf @method('DELETE')<button class="btn btn-outline-danger mb-5" type="submit"><i class="bi bi-trash"></i> Delete Record</button></form></div>
@endsection
