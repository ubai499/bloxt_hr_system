<?php

namespace App\Http\Controllers;

use Illuminate\View\View;

class DashboardController extends Controller
{
    public function admin(): View
    {
        return view('home');
    }

    public function employee(): View
    {
        return view('dashboards.employee');
    }
}
