@extends('layouts.master')
@section('title', 'Edit Leave Request | Bloxt HR')
@section('content')
    <div class="page-header-bar"><div class="breadcrumb-trail"><a href="{{ route('admin.leave.index') }}">Leave</a><span class="breadcrumb-sep">/</span>Edit Request</div><h1 class="page-title">Edit Leave Request</h1><p class="page-subtitle">Update the request details before or after a decision.</p></div>
    <div class="app-content">@include('admin.leave._form')<form method="POST" action="{{ route('admin.leave.destroy', $leaveRequest) }}" onsubmit="return confirm('Delete this leave request?')">@csrf @method('DELETE')<button class="btn btn-outline-danger mb-5" type="submit"><i class="bi bi-trash"></i> Delete Request</button></form></div>
@endsection
