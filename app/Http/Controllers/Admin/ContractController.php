<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\DocumentController;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class ContractController extends DocumentController
{
    protected function routePrefix(): string
    {
        return 'admin.contracts';
    }

    protected function defaultCategory(): string
    {
        return 'Employment Contract';
    }

    public function create(Request $request): RedirectResponse
    {
        return redirect()->route('admin.contracts.index', ['new' => 1]);
    }
}
