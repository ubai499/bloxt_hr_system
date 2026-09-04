<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Contracts\View\View;

class DashboardController extends Controller
{
    public function index(): View
    {
        $employees = User::role('employee')->get();

        return view('home', [
            'employeeCount' => $employees->count(),
            'activeEmployeeCount' => $employees->where('status', 'Active')->count(),
        ]);
    }
}
