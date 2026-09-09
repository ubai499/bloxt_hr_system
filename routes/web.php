<?php

use App\Http\Controllers\Admin\AttendanceController;
use App\Http\Controllers\Admin\ContractController;
use App\Http\Controllers\Admin\DashboardController as AdminDashboardController;
use App\Http\Controllers\Admin\DepartmentController;
use App\Http\Controllers\Admin\EmployeeController;
use App\Http\Controllers\Admin\LeaveController;
use App\Http\Controllers\Admin\PayrollController;
use App\Http\Controllers\Admin\RecruitmentController;
use App\Http\Controllers\Admin\RightToWorkController;
use App\Http\Controllers\DocumentController;
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
    Route::get('/recruitment', [RecruitmentController::class, 'index'])->name('recruitment.index');
    Route::get('/recruitment/create', [RecruitmentController::class, 'create'])->name('recruitment.create');
    Route::get('/recruitment/export', [RecruitmentController::class, 'export'])->name('recruitment.export');
    Route::post('/recruitment', [RecruitmentController::class, 'store'])->name('recruitment.store');
    Route::put('/recruitment/{vacancy}', [RecruitmentController::class, 'update'])->name('recruitment.update');
    Route::patch('/recruitment/{vacancy}/status', [RecruitmentController::class, 'updateStatus'])->name('recruitment.status.update');
    Route::delete('/recruitment/{vacancy}', [RecruitmentController::class, 'destroy'])->name('recruitment.destroy');
    Route::post('/recruitment/{vacancy}/candidates', [RecruitmentController::class, 'storeCandidate'])->name('recruitment.candidates.store');
    Route::put('/recruitment/{vacancy}/candidates/{candidate}', [RecruitmentController::class, 'updateCandidate'])->name('recruitment.candidates.update');
    Route::delete('/recruitment/{vacancy}/candidates/{candidate}', [RecruitmentController::class, 'destroyCandidate'])->name('recruitment.candidates.destroy');
    Route::get('/right-to-work', [RightToWorkController::class, 'index'])->name('right-to-work.index');
    Route::get('/right-to-work/create', [RightToWorkController::class, 'create'])->name('right-to-work.create');
    Route::get('/right-to-work/export', [RightToWorkController::class, 'export'])->name('right-to-work.export');
    Route::post('/right-to-work', [RightToWorkController::class, 'store'])->name('right-to-work.store');
    Route::get('/right-to-work/{check}', [RightToWorkController::class, 'show'])->name('right-to-work.show');
    Route::put('/right-to-work/{check}', [RightToWorkController::class, 'update'])->name('right-to-work.update');
    Route::delete('/right-to-work/{check}', [RightToWorkController::class, 'destroy'])->name('right-to-work.destroy');
    Route::get('/payroll', [PayrollController::class, 'index'])->name('payroll.index');
    Route::get('/payroll/create', [PayrollController::class, 'create'])->name('payroll.create');
    Route::get('/payroll/export', [PayrollController::class, 'export'])->name('payroll.export');
    Route::post('/payroll', [PayrollController::class, 'store'])->name('payroll.store');
    Route::get('/payroll/salaries/create', [PayrollController::class, 'createSalary'])->name('payroll.salaries.create');
    Route::get('/payroll/salaries/export', [PayrollController::class, 'exportSalaries'])->name('payroll.salaries.export');
    Route::post('/payroll/salaries', [PayrollController::class, 'storeSalary'])->name('payroll.salaries.store');
    Route::get('/payroll/salaries/{compensation}', [PayrollController::class, 'showSalary'])->name('payroll.salaries.show');
    Route::put('/payroll/salaries/{compensation}', [PayrollController::class, 'updateSalary'])->name('payroll.salaries.update');
    Route::delete('/payroll/salaries/{compensation}', [PayrollController::class, 'destroySalary'])->name('payroll.salaries.destroy');
    Route::get('/payroll/{record}', [PayrollController::class, 'show'])->name('payroll.show');
    Route::put('/payroll/{record}', [PayrollController::class, 'update'])->name('payroll.update');
    Route::delete('/payroll/{record}', [PayrollController::class, 'destroy'])->name('payroll.destroy');
    Route::get('/contracts', [ContractController::class, 'index'])->name('contracts.index');
    Route::get('/contracts/create', [ContractController::class, 'create'])->name('contracts.create');
    Route::get('/contracts/export', [ContractController::class, 'export'])->name('contracts.export');
    Route::get('/contracts/{document}/download', [ContractController::class, 'download'])->name('contracts.download');
    Route::post('/contracts', [ContractController::class, 'store'])->name('contracts.store');
    Route::get('/documents', [DocumentController::class, 'index'])->name('documents.index');
    Route::get('/documents/create', [DocumentController::class, 'create'])->name('documents.create');
    Route::get('/documents/search', [DocumentController::class, 'search'])->name('documents.search');
    Route::get('/documents/export', [DocumentController::class, 'export'])->name('documents.export');
    Route::get('/documents/{document}/download', [DocumentController::class, 'download'])->name('documents.download');
    Route::post('/documents', [DocumentController::class, 'store'])->name('documents.store');
    Route::post('/employees/{employee}/documents', [EmployeeController::class, 'storeDocument'])->name('employees.documents.store');
});

Route::middleware(['auth', 'role:employee'])->prefix('employee')->name('employee.')->group(function () {
    Route::get('/dashboard', [EmployeeDashboardController::class, 'index'])->name('dashboard');
    Route::get('/documents', [DocumentController::class, 'index'])->name('documents.index');
    Route::get('/documents/create', [DocumentController::class, 'create'])->name('documents.create');
    Route::get('/documents/search', [DocumentController::class, 'search'])->name('documents.search');
    Route::get('/documents/export', [DocumentController::class, 'export'])->name('documents.export');
    Route::get('/documents/{document}/download', [DocumentController::class, 'download'])->name('documents.download');
    Route::post('/documents', [DocumentController::class, 'store'])->name('documents.store');
});
