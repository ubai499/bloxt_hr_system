<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\EmployeeCompensation;
use App\Models\PayrollRecord;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PayrollController extends Controller
{
    public function index(Request $request): View|JsonResponse
    {
        $filters = $this->filters($request);
        $payload = [
            'salary_rows' => $this->salaryRows($filters)->values(),
            'payroll_rows' => $this->payrollRows($filters)->values(),
        ];
        if ($request->expectsJson()) {
            return response()->json($payload);
        }

        return view('admin.payroll.index', [
            'payload' => $payload,
            'filters' => $filters,
            'employees' => User::role('employee')->where('status', '!=', 'Left')->orderBy('name')->get(['id', 'name', 'weekly_hours', 'employee_number']),
            'frequencies' => EmployeeCompensation::FREQUENCIES,
            'activeTab' => $request->query('tab') === 'payroll' ? 'payroll' : 'salary',
        ]);
    }

    public function create(Request $request): RedirectResponse
    {
        return redirect()->route('admin.payroll.index', array_filter([
            'tab' => 'payroll',
            'new' => 'payroll',
            'employee' => $request->query('employee'),
        ]));
    }

    public function createSalary(Request $request): RedirectResponse
    {
        return redirect()->route('admin.payroll.index', array_filter([
            'tab' => 'salary',
            'new' => 'salary',
            'employee' => $request->query('employee'),
        ]));
    }

    public function store(Request $request): JsonResponse|RedirectResponse
    {
        $data = $this->payrollData($request);
        DB::transaction(function () use ($data) {
            $this->lockEmployee($data['employee_id']);
            PayrollRecord::create($data);
        });

        return $this->success($request, 'The payroll evidence has been added.', 'Payroll record saved', 'payroll');
    }

    public function storeSalary(Request $request): JsonResponse|RedirectResponse
    {
        $data = $this->salaryData($request);
        DB::transaction(function () use ($data) {
            $this->lockEmployee($data['employee_id']);
            EmployeeCompensation::create($data);
        });

        return $this->success($request, 'The new salary has been added to the employee\'s history.', 'Salary record saved', 'salary');
    }

    public function show(PayrollRecord $record): JsonResponse
    {
        $record->load('employee');

        return response()->json($this->payrollDetail($record));
    }

    public function showSalary(EmployeeCompensation $compensation): JsonResponse
    {
        $compensation->load('employee');

        return response()->json($this->salaryDetail($compensation));
    }

    public function update(Request $request, PayrollRecord $record): JsonResponse|RedirectResponse
    {
        $data = $this->payrollData($request, $record);
        DB::transaction(function () use ($data, $record) {
            $this->lockEmployee($record->employee_id, false);
            $row = PayrollRecord::lockForUpdate()->findOrFail($record->id);
            unset($data['employee_id']);
            $row->update($data);
        });

        return $this->success($request, 'The payroll record has been updated.', 'Payroll record updated', 'payroll');
    }

    public function updateSalary(Request $request, EmployeeCompensation $compensation): JsonResponse|RedirectResponse
    {
        $data = $this->salaryData($request, $compensation);
        DB::transaction(function () use ($data, $compensation) {
            $this->lockEmployee($compensation->employee_id, false);
            $row = EmployeeCompensation::lockForUpdate()->findOrFail($compensation->id);
            unset($data['employee_id']);
            $row->update($data);
        });

        return $this->success($request, 'The salary record has been updated.', 'Salary record updated', 'salary');
    }

    public function destroy(Request $request, PayrollRecord $record): JsonResponse|RedirectResponse
    {
        DB::transaction(function () use ($record) {
            User::whereKey($record->employee_id)->lockForUpdate()->firstOrFail();
            PayrollRecord::lockForUpdate()->findOrFail($record->id)->delete();
        });

        return $this->success($request, 'The payroll record has been removed.', 'Payroll record removed', 'payroll');
    }

    public function destroySalary(Request $request, EmployeeCompensation $compensation): JsonResponse|RedirectResponse
    {
        DB::transaction(function () use ($compensation) {
            User::whereKey($compensation->employee_id)->lockForUpdate()->firstOrFail();
            EmployeeCompensation::lockForUpdate()->findOrFail($compensation->id)->delete();
        });

        return $this->success($request, 'The salary record has been removed.', 'Salary record removed', 'salary');
    }

    public function export(Request $request): StreamedResponse
    {
        $sorting = $request->validate([
            'sort' => ['nullable', Rule::in(['employee', 'payroll_period', 'gross_salary', 'overtime', 'bonus', 'deductions', 'net_amount', 'payment_date', 'payroll_reference'])],
            'direction' => ['nullable', Rule::in(['asc', 'desc'])],
        ]);
        $rows = $this->payrollRows($this->filters($request));
        $sort = $sorting['sort'] ?? 'payment_date';
        $rows = $rows->sortBy(fn ($row) => $this->sortValue($row[$sort] ?? ''), SORT_NATURAL | SORT_FLAG_CASE, ($sorting['direction'] ?? 'desc') === 'desc');

        return $this->csvDownload('payroll-records.csv', ['Employee', 'Period', 'Gross', 'Overtime', 'Bonus', 'Deductions', 'Net', 'Payment Date', 'Reference'], $rows, function ($row) {
            return [$row['employee'], $row['payroll_period'], $row['gross_salary'], $row['overtime'], $row['bonus'], $row['deductions'], $row['net_amount'], $row['payment_date'] ?? '', $row['payroll_reference']];
        });
    }

    public function exportSalaries(Request $request): StreamedResponse
    {
        $sorting = $request->validate([
            'sort' => ['nullable', Rule::in(['employee', 'salary', 'basis', 'contracted_hours', 'effective_date', 'approved_by'])],
            'direction' => ['nullable', Rule::in(['asc', 'desc'])],
        ]);
        $rows = $this->salaryRows($this->filters($request));
        $sort = $sorting['sort'] ?? 'employee';
        $rows = $rows->sortBy(fn ($row) => $this->sortValue($row[$sort] ?? ''), SORT_NATURAL | SORT_FLAG_CASE, ($sorting['direction'] ?? 'asc') === 'desc');

        return $this->csvDownload('salary-records.csv', ['Employee', 'Current Salary', 'Basis', 'Contracted Hours', 'Effective Date', 'Approved By'], $rows, function ($row) {
            return [$row['employee'], $row['salary'], $row['basis'], $row['contracted_hours'], $row['effective_date'] ?? '', $row['approved_by']];
        });
    }

    /**
     * @return array{search: string, employee: string}
     */
    private function filters(Request $request): array
    {
        $data = $request->validate([
            'search' => ['nullable', 'string', 'max:255'],
            'employee' => ['nullable', 'regex:/^[1-9][0-9]*$/'],
        ]);

        return [
            'search' => $data['search'] ?? '',
            'employee' => $data['employee'] ?? '',
        ];
    }

    private function salaryRows(array $filters)
    {
        $search = trim($filters['search']);

        return User::role('employee')->where('status', '!=', 'Left')
            ->whereHas('latestCompensation')
            ->with('latestCompensation')
            ->when($search !== '', function (Builder $query) use ($search) {
                $query->where(function (Builder $query) use ($search) {
                    $query->where('name', 'like', '%'.$search.'%')
                        ->orWhere('employee_number', 'like', '%'.$search.'%');
                });
            })
            ->when($filters['employee'] !== '', fn (Builder $query) => $query->whereKey($filters['employee']))
            ->orderBy('name')
            ->get()
            ->map(fn (User $employee) => $this->salaryRow($employee, $employee->latestCompensation));
    }

    private function payrollRows(array $filters)
    {
        $search = trim($filters['search']);

        return PayrollRecord::query()->with('employee')
            ->when($search !== '', function (Builder $query) use ($search) {
                $query->where(function (Builder $query) use ($search) {
                    $query->where('payroll_period', 'like', '%'.$search.'%')
                        ->orWhere('payroll_reference', 'like', '%'.$search.'%')
                        ->orWhereHas('employee', function (Builder $query) use ($search) {
                            $query->where('name', 'like', '%'.$search.'%')
                                ->orWhere('employee_number', 'like', '%'.$search.'%');
                        });
                });
            })
            ->when($filters['employee'] !== '', fn (Builder $query) => $query->where('employee_id', $filters['employee']))
            ->latest('payment_date')
            ->orderByDesc('id')
            ->get()
            ->map(fn (PayrollRecord $record) => $this->payrollRow($record));
    }

    private function salaryRow(User $employee, EmployeeCompensation $record): array
    {
        return [
            'id' => $record->id,
            'employee_id' => $employee->id,
            'employee' => $employee->name,
            'salary' => $this->amount($record->annual_salary),
            'basis' => $record->salary_frequency,
            'contracted_hours' => $this->amount($record->contracted_hours),
            'effective_date' => $record->effective_date?->toDateString(),
            'approved_by' => $record->authorised_by,
            'profile_url' => route('admin.employees.show', ['employee' => $employee, 'tab' => 'salary']),
            'details_url' => route('admin.payroll.salaries.show', $record),
            'update_url' => route('admin.payroll.salaries.update', $record),
            'destroy_url' => route('admin.payroll.salaries.destroy', $record),
        ];
    }

    private function salaryDetail(EmployeeCompensation $record): array
    {
        return [
            'id' => $record->id,
            'employee_id' => $record->employee_id,
            'employee' => $record->employee->name,
            'annual_salary' => $this->amount($record->annual_salary),
            'salary_frequency' => $record->salary_frequency,
            'hourly_rate' => $this->amount($record->hourly_rate),
            'contracted_hours' => $this->amount($record->contracted_hours),
            'previous_salary' => $this->amount($record->previous_salary),
            'effective_date' => $record->effective_date->toDateString(),
            'reason' => $record->reason,
            'authorised_by' => $record->authorised_by,
            'recorded_by' => $record->recorded_by,
            'update_url' => route('admin.payroll.salaries.update', $record),
            'destroy_url' => route('admin.payroll.salaries.destroy', $record),
        ];
    }

    private function payrollRow(PayrollRecord $record): array
    {
        return [
            'id' => $record->id,
            'employee_id' => $record->employee_id,
            'employee' => $record->employee?->name,
            'payroll_period' => $record->payroll_period,
            'gross_salary' => $this->amount($record->gross_salary),
            'overtime' => $this->amount($record->overtime),
            'bonus' => $this->amount($record->bonus),
            'deductions' => $this->amount($record->deductions),
            'net_amount' => $this->amount($record->net_amount),
            'payment_date' => $record->payment_date?->toDateString(),
            'payroll_reference' => $record->payroll_reference,
            'profile_url' => route('admin.employees.show', $record->employee),
            'details_url' => route('admin.payroll.show', $record),
            'update_url' => route('admin.payroll.update', $record),
            'destroy_url' => route('admin.payroll.destroy', $record),
        ];
    }

    private function payrollDetail(PayrollRecord $record): array
    {
        return [
            'id' => $record->id,
            'employee_id' => $record->employee_id,
            'employee' => $record->employee->name,
            'payroll_period' => $record->payroll_period,
            'gross_salary' => $this->amount($record->gross_salary),
            'basic_salary' => $this->amount($record->basic_salary),
            'overtime' => $this->amount($record->overtime),
            'bonus' => $this->amount($record->bonus),
            'deductions' => $this->amount($record->deductions),
            'net_amount' => $this->amount($record->net_amount),
            'payment_date' => $record->payment_date->toDateString(),
            'payroll_reference' => $record->payroll_reference,
            'evidence_uploaded' => $record->evidence_uploaded,
            'notes' => $record->notes,
            'update_url' => route('admin.payroll.update', $record),
            'destroy_url' => route('admin.payroll.destroy', $record),
        ];
    }

    private function salaryData(Request $request, ?EmployeeCompensation $record = null): array
    {
        foreach (['hourly_rate', 'contracted_hours'] as $field) {
            if ($request->input($field) === '') {
                $request->merge([$field => null]);
            }
        }
        $data = $request->validate([
            'employee_id' => [$record ? 'nullable' : 'required', 'integer', 'exists:users,id'],
            'annual_salary' => ['required', 'numeric', 'min:0', 'max:99999999.99'],
            'salary_frequency' => ['required', Rule::in(EmployeeCompensation::FREQUENCIES)],
            'hourly_rate' => ['nullable', 'required_if:salary_frequency,Hourly', 'numeric', 'min:0', 'max:999999.99'],
            'contracted_hours' => ['nullable', 'numeric', 'min:0', 'max:168'],
            'effective_date' => ['required', 'date_format:Y-m-d'],
            'reason' => ['required', 'string', 'max:255'],
            'authorised_by' => ['nullable', 'string', 'max:255'],
        ], [
            'hourly_rate.required_if' => 'Enter an hourly rate when the salary basis is hourly.',
        ]);
        $employeeId = $record?->employee_id ?? (int) $data['employee_id'];
        $employee = User::findOrFail($employeeId);
        $data['employee_id'] = $employeeId;
        $data['annual_salary'] = $this->amount($data['annual_salary']);
        $data['hourly_rate'] = isset($data['hourly_rate']) ? $this->amount($data['hourly_rate']) : null;
        $data['contracted_hours'] = isset($data['contracted_hours'])
            ? $this->amount($data['contracted_hours'])
            : $this->amount($employee->weekly_hours);
        $data['reason'] = trim($data['reason']);
        $data['authorised_by'] = filled($data['authorised_by'] ?? null) ? trim($data['authorised_by']) : $request->user()->name;
        $data['recorded_by'] = $record?->recorded_by ?: $request->user()->name;
        $data['previous_salary'] = $this->previousSalary($employeeId, $data['effective_date'], $record?->id);

        return $data;
    }

    private function payrollData(Request $request, ?PayrollRecord $record = null): array
    {
        foreach (['basic_salary', 'overtime', 'bonus', 'deductions', 'notes'] as $field) {
            if ($request->input($field) === '') {
                $request->merge([$field => $field === 'notes' ? null : 0]);
            }
        }
        $employeeId = $record?->employee_id ?? $request->input('employee_id');
        $data = $request->validate([
            'employee_id' => [$record ? 'nullable' : 'required', 'integer', 'exists:users,id'],
            'payroll_period' => ['required', 'string', 'max:100', Rule::unique('payroll_records', 'payroll_period')->where(fn ($query) => $query->where('employee_id', $employeeId))->ignore($record)],
            'gross_salary' => ['required', 'numeric', 'min:0', 'max:99999999.99'],
            'basic_salary' => ['nullable', 'numeric', 'min:0', 'max:99999999.99'],
            'overtime' => ['nullable', 'numeric', 'min:0', 'max:99999999.99'],
            'bonus' => ['nullable', 'numeric', 'min:0', 'max:99999999.99'],
            'deductions' => ['nullable', 'numeric', 'min:0', 'max:99999999.99'],
            'payment_date' => ['required', 'date_format:Y-m-d'],
            'payroll_reference' => ['required', 'string', 'max:100', Rule::unique('payroll_records', 'payroll_reference')->ignore($record)],
            'evidence_uploaded' => ['nullable', 'boolean'],
            'notes' => ['nullable', 'string', 'max:5000'],
        ], [
            'payroll_period.unique' => 'A payroll record already exists for this employee and period.',
        ]);
        $data['employee_id'] = (int) ($record?->employee_id ?? $data['employee_id']);
        $data['payroll_period'] = trim($data['payroll_period']);
        $data['payroll_reference'] = trim($data['payroll_reference']);
        $data['notes'] = filled($data['notes'] ?? null) ? trim($data['notes']) : null;
        $data['evidence_uploaded'] = $request->boolean('evidence_uploaded');
        $data['gross_salary'] = $this->amount($data['gross_salary']);
        $data['overtime'] = $this->amount($data['overtime'] ?? 0);
        $data['bonus'] = $this->amount($data['bonus'] ?? 0);
        $data['deductions'] = $this->amount($data['deductions'] ?? 0);
        $data['basic_salary'] = isset($data['basic_salary']) && $data['basic_salary'] !== null
            ? $this->amount($data['basic_salary'])
            : $data['gross_salary'];
        $data['net_amount'] = $this->amount($data['gross_salary'] + $data['overtime'] + $data['bonus'] - $data['deductions']);
        if ($data['net_amount'] < 0) {
            throw ValidationException::withMessages(['deductions' => 'Deductions cannot exceed gross pay plus overtime and bonus.']);
        }

        return $data;
    }

    private function previousSalary(int $employeeId, string $effectiveDate, ?int $exceptId = null): ?float
    {
        $previous = EmployeeCompensation::query()
            ->where('employee_id', $employeeId)
            ->when($exceptId, fn (Builder $query) => $query->whereKeyNot($exceptId))
            ->where('effective_date', '<', $effectiveDate)
            ->orderByDesc('effective_date')
            ->orderByDesc('id')
            ->first();

        return $previous ? $this->amount($previous->annual_salary) : null;
    }

    private function lockEmployee(int $id, bool $currentOnly = true): User
    {
        $employee = User::lockForUpdate()->findOrFail($id);
        if ($currentOnly && (! $employee->hasRole('employee') || $employee->status === 'Left')) {
            throw ValidationException::withMessages(['employee_id' => 'Choose a current employee.']);
        }

        return $employee;
    }

    private function amount(mixed $value): ?float
    {
        if ($value === null || $value === '') {
            return null;
        }

        return round((float) $value, 2);
    }

    private function sortValue(mixed $value): string
    {
        return is_numeric($value) ? sprintf('%020.2f', (float) $value) : mb_strtolower((string) $value);
    }

    /**
     * @param  callable(array<string, mixed>): array<int, mixed>  $cells
     */
    private function csvDownload(string $filename, array $headers, $rows, callable $cells): StreamedResponse
    {
        return response()->streamDownload(function () use ($headers, $rows, $cells) {
            $stream = fopen('php://output', 'w');
            fputcsv($stream, $headers);
            foreach ($rows as $row) {
                fputcsv($stream, array_map(fn ($cell) => preg_match('/^[\s]*[=+@-]/u', (string) $cell) ? "'".$cell : $cell, $cells($row)));
            }
            fclose($stream);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    private function success(Request $request, string $message, string $title, string $tab): JsonResponse|RedirectResponse
    {
        return $request->expectsJson()
            ? response()->json(['message' => $message])
            : redirect()->route('admin.payroll.index', ['tab' => $tab])->with('success', $message)->with('toast_title', $title);
    }
}
