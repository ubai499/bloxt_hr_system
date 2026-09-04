<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Contracts\Support\Renderable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class HomeController extends Controller
{
    /**
     * Create a new controller instance.
     *
     * @return void
     */
    public function __construct()
    {
        $this->middleware('auth');
    }

    /**
     * Show the application dashboard.
     *
     * @return Renderable
     */
    public function index(Request $request): RedirectResponse
    {
        $user = $request->user();

        if ($user instanceof User && $user->hasRole('admin')) {
            return redirect()->route('admin.dashboard');
        }

        if ($user instanceof User && $user->hasRole('employee')) {
            return redirect()->route('employee.dashboard');
        }

        auth()->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login')->with('error', 'Your account does not have an HR role assigned.');
    }
}
