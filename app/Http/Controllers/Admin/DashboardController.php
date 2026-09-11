<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\AdminDashboard;
use Illuminate\Contracts\View\View;

class DashboardController extends Controller
{
    public function index(AdminDashboard $dashboard): View
    {
        return view('admin.dashboard', [
            'payload' => $dashboard->payload(),
        ]);
    }
}
