<?php

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
