<?php

use App\Http\Controllers\Admin\AttendanceController;
use App\Http\Controllers\Admin\DashboardController as AdminDashboardController;
use App\Http\Controllers\Admin\DepartmentController;
use App\Http\Controllers\Admin\EmployeeController;
use App\Http\Controllers\Admin\LeaveController;
use App\Http\Controllers\Employee\DashboardController as EmployeeDashboardController;
use App\Http\Controllers\HomeController;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return auth()->check()
        ? redirect()->route('home')
        : redirect()->route('login');
})->name('root');

Auth::routes();

Route::get('/home', [HomeController::class, 'index'])
    ->middleware('auth')
    ->name('home');

Route::middleware(['auth', 'role:admin'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('/dashboard', [AdminDashboardController::class, 'index'])->name('dashboard');

    Route::get('/attendance', [AttendanceController::class, 'index'])->name('attendance.index');
    Route::get('/attendance/create', [AttendanceController::class, 'create'])->name('attendance.create');
    Route::get('/attendance/export', [AttendanceController::class, 'export'])->name('attendance.export');
    Route::post('/attendance', [AttendanceController::class, 'store'])->name('attendance.store');
    Route::get('/attendance/{record}/edit', [AttendanceController::class, 'edit'])->name('attendance.edit');
    Route::put('/attendance/{record}', [AttendanceController::class, 'update'])->name('attendance.update');
    Route::patch('/attendance/{record}/review', [AttendanceController::class, 'review'])->name('attendance.review');
    Route::delete('/attendance/{record}', [AttendanceController::class, 'destroy'])->name('attendance.destroy');
    Route::get('/attendance/absence/create', [AttendanceController::class, 'createAbsence'])->name('attendance.absence.create');
    Route::post('/attendance/absence', [AttendanceController::class, 'storeAbsence'])->name('attendance.absence.store');
    Route::get('/attendance/absence/{absence}', [AttendanceController::class, 'showAbsence'])->name('attendance.absence.show');
    Route::get('/attendance/absence/{absence}/edit', [AttendanceController::class, 'editAbsence'])->name('attendance.absence.edit');
    Route::put('/attendance/absence/{absence}', [AttendanceController::class, 'updateAbsence'])->name('attendance.absence.update');
    Route::delete('/attendance/absence/{absence}', [AttendanceController::class, 'destroyAbsence'])->name('attendance.absence.destroy');
    Route::get('/leave', [LeaveController::class, 'index'])->name('leave.index');
    Route::get('/leave/create', [LeaveController::class, 'create'])->name('leave.create');
    Route::get('/leave/export', [LeaveController::class, 'export'])->name('leave.export');
    Route::post('/leave', [LeaveController::class, 'store'])->name('leave.store');
    Route::get('/leave/{leaveRequest}', [LeaveController::class, 'show'])->name('leave.show');
    Route::get('/leave/{leaveRequest}/edit', [LeaveController::class, 'edit'])->name('leave.edit');
    Route::put('/leave/{leaveRequest}', [LeaveController::class, 'update'])->name('leave.update');
    Route::patch('/leave/{leaveRequest}/status', [LeaveController::class, 'updateStatus'])->name('leave.status.update');
    Route::delete('/leave/{leaveRequest}', [LeaveController::class, 'destroy'])->name('leave.destroy');
    Route::resource('employees', EmployeeController::class);
    Route::resource('departments', DepartmentController::class);
});

Route::middleware(['auth', 'role:employee'])->prefix('employee')->name('employee.')->group(function () {
    Route::get('/dashboard', [EmployeeDashboardController::class, 'index'])->name('dashboard');
});
