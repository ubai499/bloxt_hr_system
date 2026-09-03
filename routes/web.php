<?php

use App\Http\Controllers\HomeController;
use App\Http\Controllers\DashboardController;
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

Route::get('/dashboard/admin', [DashboardController::class, 'admin'])
    ->middleware(['auth', 'role:admin'])
    ->name('dashboard.admin');

Route::get('/dashboard/employee', [DashboardController::class, 'employee'])
    ->middleware(['auth', 'role:employee'])
    ->name('dashboard.employee');
