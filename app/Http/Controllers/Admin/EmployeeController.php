<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Department;
use App\Models\EmployeeCompensation;
use App\Models\RightToWorkCheck;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Spatie\Permission\Models\Role;
use Symfony\Component\HttpFoundation\StreamedResponse;

class EmployeeController extends Controller
{
    public function index(Request $request): View|StreamedResponse
    {
        $activeTab = $request->query('tab') === 'departments' ? 'departments' : 'directory';
        $employeeQuery = $this->employeeIndexQuery($request)
            ->with(['manager', 'departmentRecord', 'latestRightToWorkCheck'])
            ->orderBy('name');

        if ($request->boolean('export')) {
            return $this->exportEmployees($employeeQuery->get());
        }

        $employees = $employeeQuery->get();

        return view('admin.employees.index', [
            'employees' => $employees,
            'departments' => Department::query()
                ->withCount([
                    'employees' => fn (Builder $query) => $query->role('employee'),
                ])
                ->orderBy('name')
                ->get(),
            'employmentTypes' => User::role('employee')
                ->whereNotNull('employment_type')
                ->where('employment_type', '!=', '')
                ->distinct()
                ->orderBy('employment_type')
                ->pluck('employment_type'),
            'workLocations' => User::role('employee')
                ->whereNotNull('work_location')
                ->where('work_location', '!=', '')
                ->distinct()
                ->orderBy('work_location')
                ->pluck('work_location'),
            'activeTab' => $activeTab,
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
        $employee = DB::transaction(function () use ($request, $validated) {
            // The wizard has no password field; provision a secure temporary credential.
            $validated['password'] = $request->filled('password')
                ? $request->input('password')
                : Str::random(32);
            $employee = User::create($validated);
            Role::findOrCreate('employee');
            $employee->assignRole('employee');

            if ($request->filled('annual_salary') && $request->filled('salary_effective_date')) {
                EmployeeCompensation::create([
                    'employee_id' => $employee->id,
                    'annual_salary' => $request->input('annual_salary'),
                    'salary_frequency' => $request->input('salary_frequency', 'Annual'),
                    'hourly_rate' => $request->input('hourly_rate'),
                    'effective_date' => $request->input('salary_effective_date'),
                    'reason' => $request->input('salary_reason'),
                    'authorised_by' => $request->input('authorised_by'),
                ]);
            }

            if ($request->input('rtw_required') === 'yes') {
                RightToWorkCheck::create([
                    'employee_id' => $employee->id,
                    'check_date' => $request->input('rtw_check_date'),
                    'check_method' => $request->input('rtw_check_type'),
                    'performed_by' => $request->input('rtw_checked_by'),
                    'immigration_category' => $request->input('immigration_status'),
                    'permission_start' => $request->input('permission_start'),
                    'permission_expiry' => $request->input('permission_expiry'),
                    'follow_up_required' => $request->input('rtw_follow_up') === 'yes',
                    'next_check_date' => $request->input('rtw_next_review'),
                    'evidence_reference' => $request->input('evidence_reference'),
                    'status' => $request->input('permission_expiry') ? 'Review Due' : 'Valid',
                    'notes' => $request->input('rtw_notes'),
                ]);
            }
            return $employee;
        });

        return redirect()->route('admin.employees.show', $employee)
            ->with('success', 'Employee created successfully. They can now sign in with their email and password.')->with('toast_title', 'Employee created');
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
            ->with('success', 'Employee updated successfully.')->with('toast_title', 'Employee updated');
    }

    public function destroy(User $employee): RedirectResponse
    {
        $this->ensureEmployee($employee);
        $employee->syncRoles([]);
        $employee->delete();

        return redirect()->route('admin.employees.index')->with('success', 'Employee deleted successfully.')->with('toast_title', 'Employee deleted');
    }

    /**
     * @return array<string, mixed>
     */
    private function validatedData(Request $request, ?User $employee = null): array
    {
        // The onboarding wizard creates the account with a secure temporary password.
        // Administrators can still set or replace a password through the edit workflow.
        $passwordRules = ['nullable', 'string', 'min:8', 'confirmed'];

        foreach (['end_date', 'probation_end_date', 'permission_start', 'permission_expiry', 'rtw_check_date', 'rtw_next_review'] as $field) {
            if ($request->input($field) === '') {
                $request->merge([$field => null]);
            }
        }

        $validated = $request->validate([
            'employee_number' => ['nullable', 'string', 'max:50', Rule::unique('users')->ignore($employee)],
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users')->ignore($employee)],
            'phone' => ['nullable', 'string', 'max:50'],
            'address' => ['nullable', 'string'],
            'personal_email' => ['nullable', 'email', 'max:255'],
            'date_of_birth' => ['nullable', 'date', 'before:today'],
            'nationality' => ['nullable', 'string', 'max:255'],
            'postcode' => ['nullable', 'string', 'max:20'],
            'emergency_contact_name' => ['nullable', 'string', 'max:255'],
            'emergency_contact_relationship' => ['nullable', 'string', 'max:255'],
            'emergency_contact_phone' => ['nullable', 'string', 'max:50'],
            'job_title' => ['required', 'string', 'max:255'],
            'department_id' => ['required', 'exists:departments,id'],
            'employment_type' => ['required', Rule::in(['Full-time', 'Part-time', 'Contract', 'Temporary'])],
            'work_location' => ['nullable', 'string', 'max:255'],
            'manager_id' => ['nullable', 'exists:users,id', Rule::notIn([$employee?->id])],
            'start_date' => ['nullable', 'date'],
            'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
            'probation_end_date' => ['nullable', 'date'],
            'work_arrangement' => ['nullable', Rule::in(['Office-based', 'Hybrid', 'Remote'])],
            'weekly_hours' => ['nullable', 'numeric', 'min:0', 'max:168'],
            'normal_working_hours' => ['nullable', 'string', 'max:255'],
            'status' => ['required', Rule::in(['Active', 'On Leave', 'Probation', 'Left'])],
            'annual_salary' => ['nullable', 'numeric', 'min:0'],
            'salary_frequency' => ['nullable', Rule::in(['Annual', 'Hourly', 'Monthly'])],
            'hourly_rate' => ['nullable', 'numeric', 'min:0'],
            'salary_effective_date' => ['nullable', 'date'],
            'rtw_required' => ['nullable', Rule::in(['yes', 'no'])],
            'rtw_check_type' => ['nullable', Rule::in(['Online Home Office check', 'Manual document check', 'Other permitted method'])],
            'rtw_check_date' => ['nullable', 'date'],
            'permission_start' => ['nullable', 'date'],
            'permission_expiry' => ['nullable', 'date', 'after_or_equal:permission_start'],
            'password' => $passwordRules,
        ]);

        return [
            'employee_number' => filled($validated['employee_number'] ?? null) ? trim($validated['employee_number']) : null,
            'name' => trim($validated['name']),
            'email' => trim($validated['email']),
            'phone' => filled($validated['phone'] ?? null) ? trim($validated['phone']) : null,
            'address' => filled($validated['address'] ?? null) ? trim($validated['address']) : null,
            'personal_email' => filled($validated['personal_email'] ?? null) ? trim($validated['personal_email']) : null,
            'date_of_birth' => $validated['date_of_birth'] ?? null,
            'nationality' => filled($validated['nationality'] ?? null) ? trim($validated['nationality']) : null,
            'postcode' => filled($validated['postcode'] ?? null) ? trim($validated['postcode']) : null,
            'emergency_contact_name' => filled($validated['emergency_contact_name'] ?? null) ? trim($validated['emergency_contact_name']) : null,
            'emergency_contact_relationship' => filled($validated['emergency_contact_relationship'] ?? null) ? trim($validated['emergency_contact_relationship']) : null,
            'emergency_contact_phone' => filled($validated['emergency_contact_phone'] ?? null) ? trim($validated['emergency_contact_phone']) : null,
            'job_title' => trim($validated['job_title']),
            'department_id' => $validated['department_id'],
            'employment_type' => $validated['employment_type'],
            'work_location' => filled($validated['work_location'] ?? null) ? trim($validated['work_location']) : null,
            'manager_id' => $validated['manager_id'] ?? null,
            'start_date' => $validated['start_date'] ?? null,
            'end_date' => $validated['end_date'] ?? null,
            'probation_end_date' => $validated['probation_end_date'] ?? null,
            'work_arrangement' => $validated['work_arrangement'] ?? null,
            'weekly_hours' => $validated['weekly_hours'] ?? null,
            'normal_working_hours' => filled($validated['normal_working_hours'] ?? null) ? trim($validated['normal_working_hours']) : null,
            'status' => $validated['status'],
        ];
    }

    private function ensureEmployee(User $employee): void
    {
        abort_unless($employee->hasRole('employee'), 404);
    }

    private function employeeIndexQuery(Request $request): Builder
    {
        $search = trim((string) $request->input('search'));

        return User::role('employee')
            ->when($search !== '', function (Builder $query) use ($search) {
                $query->where(function (Builder $query) use ($search) {
                    $query->where('name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%")
                        ->orWhere('employee_number', 'like', "%{$search}%")
                        ->orWhere('job_title', 'like', "%{$search}%");
                });
            })
            ->when($request->filled('department'), fn (Builder $query) => $query->where('department_id', $request->department))
            ->when($request->filled('status'), fn (Builder $query) => $query->where('status', $request->status))
            ->when($request->filled('work_location'), fn (Builder $query) => $query->where('work_location', $request->work_location))
            ->when($request->filled('employment_type'), fn (Builder $query) => $query->where('employment_type', $request->employment_type))
            ->when($request->boolean('sponsored'), function (Builder $query) {
                $query->whereHas('latestRightToWorkCheck', function (Builder $query) {
                    $query->where('check_method', 'Online Home Office check')
                        ->whereNotNull('permission_expiry');
                });
            });
    }

    private function exportEmployees($employees): StreamedResponse
    {
        return response()->streamDownload(function () use ($employees) {
            $handle = fopen('php://output', 'w');

            fputcsv($handle, [
                'Employee ID',
                'Employee',
                'Email',
                'Job Title',
                'Department',
                'Manager',
                'Employment Type',
                'Work Location',
                'Start Date',
                'Status',
                'Right to Work',
            ]);

            foreach ($employees as $employee) {
                fputcsv($handle, [
                    $employee->employee_number,
                    $employee->name,
                    $employee->email,
                    $employee->job_title,
                    $employee->departmentRecord?->name,
                    $employee->manager?->name,
                    $employee->employment_type,
                    $employee->work_location,
                    $employee->start_date?->format('Y-m-d'),
                    $employee->status,
                    $employee->latestRightToWorkCheck?->directoryStatus() ?? 'Evidence Missing',
                ]);
            }

            fclose($handle);
        }, 'employee-directory.csv', [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }
}
