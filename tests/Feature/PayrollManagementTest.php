<?php

namespace Tests\Feature;

use App\Models\Department;
use App\Models\EmployeeCompensation;
use App\Models\PayrollRecord;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class PayrollManagementTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $employee;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(CarbonImmutable::parse('2026-09-09 12:00:00'));
        Role::findOrCreate('admin');
        Role::findOrCreate('employee');
        $this->admin = User::factory()->create(['name' => 'Finance Administrator']);
        $this->admin->assignRole('admin');
        $department = Department::create(['name' => 'Finance', 'status' => 'Active']);
        $this->employee = User::factory()->create([
            'name' => 'Priya Sharma',
            'status' => 'Active',
            'employee_number' => 'BXT-104',
            'weekly_hours' => 37.5,
            'department_id' => $department->id,
        ]);
        $this->employee->assignRole('employee');
        $this->actingAs($this->admin);
    }

    public function test_page_matches_the_prototype_and_create_opens_the_modal(): void
    {
        $this->get(route('admin.payroll.index'))->assertOk()
            ->assertSee('Payroll')->assertSee('Salary Records')->assertSee('Payroll Records')
            ->assertSee('id="salaryTable"', false)->assertSee('id="payrollTable"', false)
            ->assertSee('id="salaryForm"', false)->assertSee('id="payrollForm"', false)
            ->assertSee('never overwrites a previous entry')
            ->assertSee('not a substitute for payroll')
            ->assertSee('admin-payroll.js')
            ->assertDontSee('assets/js/storage.js')->assertDontSee('HR.db');
        $this->get(route('admin.payroll.salaries.create', ['employee' => $this->employee->id]))
            ->assertRedirect(route('admin.payroll.index', ['tab' => 'salary', 'new' => 'salary', 'employee' => $this->employee->id]));
        $this->get(route('admin.payroll.create', ['employee' => $this->employee->id]))
            ->assertRedirect(route('admin.payroll.index', ['tab' => 'payroll', 'new' => 'payroll', 'employee' => $this->employee->id]));
        $this->get(route('admin.payroll.index', ['tab' => 'payroll']))->assertOk()
            ->assertSee('aria-controls="tab-payroll"', false);
    }

    public function test_current_salaries_are_listed_and_new_entries_do_not_overwrite_history(): void
    {
        $this->postJson(route('admin.payroll.salaries.store'), $this->salaryPayload([
            'employee_id' => $this->admin->id,
        ]))->assertUnprocessable()->assertJsonValidationErrors('employee_id');

        $this->postJson(route('admin.payroll.salaries.store'), $this->salaryPayload())->assertOk()
            ->assertJsonPath('message', 'The new salary has been added to the employee\'s history.');
        $this->postJson(route('admin.payroll.salaries.store'), $this->salaryPayload([
            'annual_salary' => 42000,
            'effective_date' => '2026-09-01',
            'reason' => 'Annual performance review increase',
        ]))->assertOk();

        $this->assertDatabaseCount('employee_compensations', 2);
        $latest = $this->employee->latestCompensation;
        $this->assertSame(42000.0, (float) $latest->annual_salary);
        $this->assertSame(38000.0, (float) $latest->previous_salary);
        $this->assertSame(37.5, (float) $latest->contracted_hours);
        $this->getJson(route('admin.payroll.index'))->assertOk()
            ->assertJsonCount(1, 'salary_rows')
            ->assertJsonPath('salary_rows.0.employee', 'Priya Sharma')
            ->assertJsonPath('salary_rows.0.salary', 42000)
            ->assertJsonPath('salary_rows.0.basis', 'Annual');
        $this->getJson(route('admin.payroll.salaries.show', $latest))->assertOk()
            ->assertJsonPath('previous_salary', 38000)
            ->assertJsonPath('reason', 'Annual performance review increase');
    }

    public function test_left_employees_and_non_employees_cannot_receive_new_salary_or_payroll_records(): void
    {
        $left = User::factory()->create(['status' => 'Left']);
        $left->assignRole('employee');
        foreach ([$this->admin, $left] as $user) {
            $this->postJson(route('admin.payroll.salaries.store'), $this->salaryPayload(['employee_id' => $user->id]))
                ->assertUnprocessable()->assertJsonValidationErrors('employee_id');
            $this->postJson(route('admin.payroll.store'), $this->payrollPayload(['employee_id' => $user->id]))
                ->assertUnprocessable()->assertJsonValidationErrors('employee_id');
        }
    }

    public function test_salary_and_payroll_fields_are_validated(): void
    {
        foreach ([
            [['annual_salary' => -10], 'annual_salary'],
            [['salary_frequency' => 'Weekly'], 'salary_frequency'],
            [['salary_frequency' => 'Hourly', 'hourly_rate' => null], 'hourly_rate'],
            [['reason' => ''], 'reason'],
        ] as [$changes, $error]) {
            $this->postJson(route('admin.payroll.salaries.store'), $this->salaryPayload($changes))
                ->assertUnprocessable()->assertJsonValidationErrors($error);
        }

        foreach ([
            [['gross_salary' => -1], 'gross_salary'],
            [['deductions' => 10000], 'deductions'],
            [['payroll_reference' => ''], 'payroll_reference'],
            [['payment_date' => '09-09-2026'], 'payment_date'],
        ] as [$changes, $error]) {
            $this->postJson(route('admin.payroll.store'), $this->payrollPayload($changes))
                ->assertUnprocessable()->assertJsonValidationErrors($error);
        }

        $this->assertDatabaseCount('employee_compensations', 0);
        $this->assertDatabaseCount('payroll_records', 0);
    }

    public function test_payroll_evidence_is_recorded_and_net_is_calculated(): void
    {
        $this->postJson(route('admin.payroll.store'), $this->payrollPayload())->assertOk()
            ->assertJsonPath('message', 'The payroll evidence has been added.');
        $record = PayrollRecord::first();
        $this->assertSame(2730.0, (float) $record->net_amount);
        $this->assertSame(3500.0, (float) $record->basic_salary);
        $this->assertTrue($record->evidence_uploaded);
        $this->getJson(route('admin.payroll.index', ['tab' => 'payroll']))->assertOk()
            ->assertJsonCount(1, 'payroll_rows')
            ->assertJsonPath('payroll_rows.0.payroll_period', 'July 2026')
            ->assertJsonPath('payroll_rows.0.net_amount', 2730);
        $this->getJson(route('admin.payroll.show', $record))->assertOk()
            ->assertJsonPath('payroll_reference', 'PR-2026-07-BXT-104')
            ->assertJsonPath('notes', 'July cycle evidence.');
    }

    public function test_duplicate_period_and_reference_are_rejected(): void
    {
        $this->postJson(route('admin.payroll.store'), $this->payrollPayload())->assertOk();
        $this->postJson(route('admin.payroll.store'), $this->payrollPayload([
            'payroll_reference' => 'PR-2026-07-OTHER',
        ]))->assertUnprocessable()->assertJsonValidationErrors('payroll_period');
        $this->postJson(route('admin.payroll.store'), $this->payrollPayload([
            'payroll_period' => 'August 2026',
        ]))->assertUnprocessable()->assertJsonValidationErrors('payroll_reference');
    }

    public function test_salary_and_payroll_records_can_be_updated_and_removed(): void
    {
        $salary = EmployeeCompensation::create($this->salaryPayload() + ['recorded_by' => 'Finance Administrator']);
        $this->putJson(route('admin.payroll.salaries.update', $salary), $this->salaryPayload([
            'employee_id' => $this->admin->id,
            'annual_salary' => 39000,
            'reason' => 'Corrected starting salary',
        ]))->assertOk();
        $this->assertSame($this->employee->id, $salary->fresh()->employee_id);
        $this->assertSame(39000.0, (float) $salary->fresh()->annual_salary);
        $this->deleteJson(route('admin.payroll.salaries.destroy', $salary))->assertOk();
        $this->assertDatabaseCount('employee_compensations', 0);

        $record = PayrollRecord::create($this->payrollModel());
        $this->putJson(route('admin.payroll.update', $record), $this->payrollPayload([
            'employee_id' => $this->admin->id,
            'bonus' => 200,
            'notes' => 'Bonus added after review.',
        ]))->assertOk();
        $this->assertSame($this->employee->id, $record->fresh()->employee_id);
        $this->assertSame(2930.0, (float) $record->fresh()->net_amount);
        $this->deleteJson(route('admin.payroll.destroy', $record))->assertOk();
        $this->assertDatabaseCount('payroll_records', 0);
    }

    public function test_filters_apply_to_rows_and_export(): void
    {
        $other = User::factory()->create(['name' => 'Alex Morgan', 'status' => 'Active', 'employee_number' => 'BXT-101', 'weekly_hours' => 37.5]);
        $other->assignRole('employee');
        EmployeeCompensation::create($this->salaryPayload(['authorised_by' => '=HYPERLINK("example")']));
        EmployeeCompensation::create($this->salaryPayload(['employee_id' => $other->id, 'annual_salary' => 29500]));
        PayrollRecord::create($this->payrollModel(['payroll_reference' => '=CMD("bad")']));
        PayrollRecord::create($this->payrollModel([
            'employee_id' => $other->id,
            'payroll_period' => 'August 2026',
            'payroll_reference' => 'PR-2026-08-BXT-101',
        ]));

        $this->getJson(route('admin.payroll.index', ['search' => 'Priya']))->assertOk()
            ->assertJsonCount(1, 'salary_rows')
            ->assertJsonCount(1, 'payroll_rows')
            ->assertJsonPath('salary_rows.0.employee', 'Priya Sharma')
            ->assertJsonPath('payroll_rows.0.employee', 'Priya Sharma');

        $salaryExport = $this->get(route('admin.payroll.salaries.export', ['search' => 'Priya']))->assertOk()
            ->assertDownload('salary-records.csv')->streamedContent();
        $this->assertStringContainsString('Priya Sharma', $salaryExport);
        $this->assertStringContainsString("'=HYPERLINK", $salaryExport);
        $this->assertStringNotContainsString('Alex Morgan', $salaryExport);

        $payrollExport = $this->get(route('admin.payroll.export', ['search' => 'Priya']))->assertOk()
            ->assertDownload('payroll-records.csv')->streamedContent();
        $this->assertStringContainsString('Priya Sharma', $payrollExport);
        $this->assertStringContainsString("'=CMD", $payrollExport);
        $this->assertStringNotContainsString('Alex Morgan', $payrollExport);
    }

    public function test_employee_profile_lists_salary_history(): void
    {
        EmployeeCompensation::create($this->salaryPayload(['effective_date' => '2023-09-04', 'reason' => 'Starting salary']));
        EmployeeCompensation::create($this->salaryPayload([
            'annual_salary' => 42000, 'effective_date' => '2025-09-04', 'reason' => 'Annual review', 'previous_salary' => 38000,
        ]));
        $this->get(route('admin.employees.show', ['employee' => $this->employee, 'tab' => 'salary']))->assertOk()
            ->assertSee('id="employeeSalary"', false)
            ->assertSee('Current Salary')->assertSee('Salary History')
            ->assertSee('Starting salary')->assertSee('Annual review')
            ->assertSee(route('admin.payroll.salaries.create', ['employee' => $this->employee->id]), false);
    }

    public function test_employees_and_other_roles_cannot_use_payroll(): void
    {
        $salary = EmployeeCompensation::create($this->salaryPayload() + ['recorded_by' => 'Finance Administrator']);
        $record = PayrollRecord::create($this->payrollModel());
        Role::findOrCreate('auditor');
        $auditor = User::factory()->create();
        $auditor->assignRole('auditor');
        foreach ([$this->employee, $auditor] as $user) {
            $this->actingAs($user);
            $this->get(route('admin.payroll.index'))->assertForbidden();
            $this->get(route('admin.payroll.export'))->assertForbidden();
            $this->get(route('admin.payroll.salaries.export'))->assertForbidden();
            $this->getJson(route('admin.payroll.show', $record))->assertForbidden();
            $this->getJson(route('admin.payroll.salaries.show', $salary))->assertForbidden();
            $this->postJson(route('admin.payroll.store'), $this->payrollPayload())->assertForbidden();
            $this->postJson(route('admin.payroll.salaries.store'), $this->salaryPayload())->assertForbidden();
            $this->putJson(route('admin.payroll.update', $record), $this->payrollPayload())->assertForbidden();
            $this->deleteJson(route('admin.payroll.destroy', $record))->assertForbidden();
        }
    }

    private function salaryPayload(array $overrides = []): array
    {
        return array_replace([
            'employee_id' => $this->employee->id,
            'annual_salary' => 38000,
            'salary_frequency' => 'Annual',
            'hourly_rate' => null,
            'contracted_hours' => 37.5,
            'effective_date' => '2023-09-04',
            'reason' => 'Starting salary',
            'authorised_by' => 'Finance Administrator',
        ], $overrides);
    }

    private function payrollPayload(array $overrides = []): array
    {
        return array_replace([
            'employee_id' => $this->employee->id,
            'payroll_period' => 'July 2026',
            'gross_salary' => 3500,
            'basic_salary' => 3500,
            'overtime' => 0,
            'bonus' => 0,
            'deductions' => 770,
            'payment_date' => '2026-07-28',
            'payroll_reference' => 'PR-2026-07-BXT-104',
            'evidence_uploaded' => true,
            'notes' => 'July cycle evidence.',
        ], $overrides);
    }

    private function payrollModel(array $overrides = []): array
    {
        return array_replace($this->payrollPayload(), [
            'net_amount' => 2730,
        ], $overrides);
    }
}
