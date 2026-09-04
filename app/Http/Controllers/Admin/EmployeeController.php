<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Department;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Spatie\Permission\Models\Role;

class EmployeeController extends Controller
{
    public function index(Request $request): View
    {
        $employees = User::role('employee')
            ->when($request->filled('search'), function ($query) use ($request) {
                $search = $request->string('search')->trim();

                $query->where(function ($query) use ($search) {
                    $query->where('name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%")
                        ->orWhere('employee_number', 'like', "%{$search}%")
                        ->orWhere('job_title', 'like', "%{$search}%");
                });
            })
            ->when($request->filled('department'), fn ($query) => $query->where('department_id', $request->department))
            ->when($request->filled('status'), fn ($query) => $query->where('status', $request->status))
            ->with(['manager', 'departmentRecord'])
            ->orderBy('name')
            ->paginate(12)
            ->withQueryString();

        return view('admin.employees.index', [
            'employees' => $employees,
            'departments' => Department::query()->orderBy('name')->get(),
        ]);
    }

    public function create(): View
    {
        return view('admin.employees.create', [
            'employee' => new User([
                'employment_type' => 'Full-time',
                'status' => 'Active',
            ]),
            'managers' => User::role('employee')->orderBy('name')->get(),
            'departments' => Department::active()->orderBy('name')->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $this->validatedData($request);
        $employee = User::create($validated + ['password' => $request->input('password')]);

        Role::findOrCreate('employee');
        $employee->assignRole('employee');

        return redirect()->route('admin.employees.show', $employee)
            ->with('success', 'Employee created successfully. They can now sign in with their email and password.');
    }

    public function show(User $employee): View
    {
        $this->ensureEmployee($employee);
        $employee->load(['manager', 'departmentRecord']);

        return view('admin.employees.show', [
            'employee' => $employee,
        ]);
    }

    public function edit(User $employee): View
    {
        $this->ensureEmployee($employee);

        return view('admin.employees.edit', [
            'employee' => $employee,
            'managers' => User::role('employee')->whereKeyNot($employee->id)->orderBy('name')->get(),
            'departments' => Department::active()->orderBy('name')->get(),
        ]);
    }

    public function update(Request $request, User $employee): RedirectResponse
    {
        $this->ensureEmployee($employee);
        $validated = $this->validatedData($request, $employee);

        if ($request->filled('password')) {
            $validated['password'] = $request->input('password');
        }

        $employee->update($validated);

        return redirect()->route('admin.employees.show', $employee)
            ->with('success', 'Employee updated successfully.');
    }

    public function destroy(User $employee): RedirectResponse
    {
        $this->ensureEmployee($employee);
        $employee->syncRoles([]);
        $employee->delete();

        return redirect()->route('admin.employees.index')->with('success', 'Employee deleted successfully.');
    }

    /**
     * @return array<string, mixed>
     */
    private function validatedData(Request $request, ?User $employee = null): array
    {
        $passwordRules = $employee
            ? ['nullable', 'string', 'min:8', 'confirmed']
            : ['required', 'string', 'min:8', 'confirmed'];

        $validated = $request->validate([
            'employee_number' => ['nullable', 'string', 'max:50', Rule::unique('users')->ignore($employee)],
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users')->ignore($employee)],
            'phone' => ['nullable', 'string', 'max:50'],
            'address' => ['nullable', 'string'],
            'job_title' => ['required', 'string', 'max:255'],
            'department_id' => ['required', 'exists:departments,id'],
            'employment_type' => ['required', Rule::in(['Full-time', 'Part-time', 'Contract', 'Temporary'])],
            'work_location' => ['nullable', 'string', 'max:255'],
            'manager_id' => ['nullable', 'exists:users,id', Rule::notIn([$employee?->id])],
            'start_date' => ['nullable', 'date'],
            'status' => ['required', Rule::in(['Active', 'On Leave', 'Probation', 'Left'])],
            'password' => $passwordRules,
        ]);

        return [
            'employee_number' => filled($validated['employee_number'] ?? null) ? trim($validated['employee_number']) : null,
            'name' => trim($validated['name']),
            'email' => trim($validated['email']),
            'phone' => filled($validated['phone'] ?? null) ? trim($validated['phone']) : null,
            'address' => filled($validated['address'] ?? null) ? trim($validated['address']) : null,
            'job_title' => trim($validated['job_title']),
            'department_id' => $validated['department_id'],
            'employment_type' => $validated['employment_type'],
            'work_location' => filled($validated['work_location'] ?? null) ? trim($validated['work_location']) : null,
            'manager_id' => $validated['manager_id'] ?? null,
            'start_date' => $validated['start_date'] ?? null,
            'status' => $validated['status'],
        ];
    }

    private function ensureEmployee(User $employee): void
    {
        abort_unless($employee->hasRole('employee'), 404);
    }
}
