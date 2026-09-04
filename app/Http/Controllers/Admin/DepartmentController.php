<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Department;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class DepartmentController extends Controller
{
    public function index(Request $request): View
    {
        $departments = Department::query()
            ->when($request->filled('search'), fn ($query) => $query->where('name', 'like', '%'.$request->search.'%'))
            ->when($request->filled('status'), fn ($query) => $query->where('status', $request->status))
            ->withCount('employees')
            ->orderBy('name')
            ->paginate(12)
            ->withQueryString();

        return view('admin.departments.index', [
            'departments' => $departments,
        ]);
    }

    public function create(): View
    {
        return view('admin.departments.create', [
            'department' => new Department(['status' => 'Active']),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $department = Department::create($this->validatedData($request));

        if ($request->boolean('return_to_employee_directory')) {
            return redirect()->route('admin.employees.index', ['tab' => 'departments'])
                ->with('success', 'Department created successfully.');
        }

        return redirect()->route('admin.departments.show', $department)
            ->with('success', 'Department created successfully.');
    }

    public function show(Department $department): View
    {
        return view('admin.departments.show', [
            'department' => $department,
            'employees' => $department->employees()->role('employee')->orderBy('name')->paginate(12),
        ]);
    }

    public function edit(Department $department): View
    {
        return view('admin.departments.edit', [
            'department' => $department,
        ]);
    }

    public function update(Request $request, Department $department): RedirectResponse
    {
        $department->update($this->validatedData($request, $department));

        if ($request->boolean('return_to_employee_directory')) {
            return redirect()->route('admin.employees.index', ['tab' => 'departments'])
                ->with('success', 'Department updated successfully.');
        }

        return redirect()->route('admin.departments.show', $department)
            ->with('success', 'Department updated successfully.');
    }

    public function destroy(Department $department): RedirectResponse
    {
        if ($department->employees()->exists()) {
            return redirect()->route('admin.departments.show', $department)
                ->with('error', 'Move employees to another department before deleting this department.');
        }

        $department->delete();

        return redirect()->route('admin.departments.index')->with('success', 'Department deleted successfully.');
    }

    /**
     * @return array<string, mixed>
     */
    private function validatedData(Request $request, ?Department $department = null): array
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255', Rule::unique('departments')->ignore($department)],
            'status' => ['required', Rule::in(['Active', 'Inactive'])],
        ]);

        return [
            'name' => trim($validated['name']),
            'status' => $validated['status'],
        ];
    }
}
