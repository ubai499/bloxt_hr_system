<?php

namespace Tests\Feature;

use App\Models\Department;
use App\Models\RightToWorkCheck;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class RightToWorkManagementTest extends TestCase
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
        $this->admin = User::factory()->create(['name' => 'Compliance Administrator']);
        $this->admin->assignRole('admin');
        $department = Department::create(['name' => 'Operations', 'status' => 'Active']);
        $this->employee = User::factory()->create([
            'name' => 'Priya Sharma',
            'status' => 'Active',
            'nationality' => 'Indian',
            'department_id' => $department->id,
        ]);
        $this->employee->assignRole('employee');
        $this->actingAs($this->admin);
    }

    public function test_page_matches_the_prototype_and_create_opens_the_modal(): void
    {
        $this->get(route('admin.right-to-work.index'))->assertOk()
            ->assertSee('Right to Work')->assertSee('Record Right-to-Work Check')
            ->assertSee('id="rtwTable"', false)->assertSee('id="rtwForm"', false)
            ->assertSee('id="rtwDetailsModal"', false)->assertSee('admin-right-to-work.js')
            ->assertSee('never inferred from nationality')
            ->assertDontSee('assets/js/storage.js')->assertDontSee('HR.db');
        $this->get(route('admin.right-to-work.create', ['employee' => $this->employee->id]))
            ->assertRedirect(route('admin.right-to-work.index', ['new' => 1, 'employee' => $this->employee->id]));
    }

    public function test_employees_without_a_check_are_listed_as_evidence_missing(): void
    {
        $this->getJson(route('admin.right-to-work.index'))->assertOk()
            ->assertJsonPath('stats.missing', 1)
            ->assertJsonPath('rows.0.status', 'Evidence Missing')
            ->assertJsonPath('rows.0.employee', 'Priya Sharma')
            ->assertJsonPath('rows.0.check_id', null);
    }

    public function test_new_checks_are_added_and_do_not_overwrite_previous_records(): void
    {
        $this->postJson(route('admin.right-to-work.store'), $this->payload([
            'status' => 'Expired', 'employee_id' => $this->admin->id,
        ]))->assertUnprocessable()->assertJsonValidationErrors('employee_id');

        $this->postJson(route('admin.right-to-work.store'), $this->payload(['status' => 'Expired']))
            ->assertOk()->assertJsonPath('message', 'The new check has been added to the employee\'s record.');
        $this->postJson(route('admin.right-to-work.store'), $this->payload([
            'check_date' => '2026-09-08', 'permission_expiry' => '2026-10-01', 'evidence_reference' => 'Share code ABC',
        ]))->assertOk();

        $this->assertDatabaseCount('right_to_work_checks', 2);
        $latest = $this->employee->latestRightToWorkCheck;
        $this->assertSame('2026-09-09', $latest->check_date->toDateString());
        $this->assertSame('Valid', $latest->status);
        $this->getJson(route('admin.right-to-work.index'))->assertJsonPath('rows.0.status', 'Valid')
            ->assertJsonPath('rows.0.evidence', true);
        $this->getJson(route('admin.right-to-work.show', $latest))->assertOk()
            ->assertJsonPath('immigration_category', 'Skilled Worker')
            ->assertJsonPath('restrictions', 'Sponsoring employer only');
    }

    public function test_status_is_derived_from_permission_dates_not_nationality(): void
    {
        foreach ([
            ['2026-09-08', 'Expired'],
            ['2026-09-20', 'Expiring Soon'],
            ['2026-11-01', 'Review Due'],
            ['2027-09-09', 'Valid'],
        ] as [$expiry, $status]) {
            $check = RightToWorkCheck::create($this->payload(['permission_expiry' => $expiry, 'status' => 'Valid']));
            $this->assertSame($status, $check->displayStatus());
            $check->delete();
        }

        RightToWorkCheck::create($this->payload(['permission_expiry' => '2027-09-09', 'follow_up_required' => true, 'next_check_date' => '2026-10-01']));
        $this->getJson(route('admin.right-to-work.index'))->assertJsonPath('rows.0.status', 'Follow-up Required')
            ->assertJsonPath('stats.due', 1);
    }

    public function test_left_employees_and_non_employees_cannot_receive_new_checks(): void
    {
        $left = User::factory()->create(['status' => 'Left']);
        $left->assignRole('employee');
        foreach ([$this->admin, $left] as $user) {
            $this->postJson(route('admin.right-to-work.store'), $this->payload(['employee_id' => $user->id]))
                ->assertUnprocessable()->assertJsonValidationErrors('employee_id');
        }
    }

    public function test_follow_up_and_permission_dates_are_validated(): void
    {
        foreach ([
            [['check_date' => '2026-09-10'], 'check_date'],
            [['permission_start' => '2026-09-01', 'permission_expiry' => '2026-08-01'], 'permission_expiry'],
            [['follow_up_required' => '1', 'next_check_date' => null], 'next_check_date'],
            [['check_method' => 'Passport glance'], 'check_method'],
        ] as [$changes, $error]) {
            $this->postJson(route('admin.right-to-work.store'), $this->payload($changes))
                ->assertUnprocessable()->assertJsonValidationErrors($error);
        }
        $this->assertDatabaseCount('right_to_work_checks', 0);
    }

    public function test_a_check_can_be_updated_and_removed(): void
    {
        $check = RightToWorkCheck::create($this->payload());
        $this->putJson(route('admin.right-to-work.update', $check), $this->payload([
            'employee_id' => $this->admin->id,
            'immigration_category' => 'British Citizen',
            'evidence_reference' => 'Passport seen',
            'notes' => 'Corrected category.',
        ]))->assertOk();
        $this->assertSame($this->employee->id, $check->fresh()->employee_id);
        $this->assertSame('British Citizen', $check->fresh()->immigration_category);
        $this->deleteJson(route('admin.right-to-work.destroy', $check))->assertOk();
        $this->assertDatabaseCount('right_to_work_checks', 0);
    }

    public function test_filters_apply_to_rows_stats_and_export(): void
    {
        $other = User::factory()->create(['name' => 'Alex Morgan', 'status' => 'Active', 'nationality' => 'British']);
        $other->assignRole('employee');
        RightToWorkCheck::create($this->payload(['performed_by' => '=HYPERLINK("example")']));
        RightToWorkCheck::create($this->payload([
            'employee_id' => $other->id, 'permission_expiry' => '2026-09-01', 'immigration_category' => 'British Citizen',
        ]));
        $filters = ['status' => 'Valid', 'search' => 'Priya'];
        $this->getJson(route('admin.right-to-work.index', $filters))->assertJsonCount(1, 'rows')
            ->assertJsonPath('stats', ['total' => 1, 'due' => 0, 'expired' => 0, 'missing' => 0]);
        $export = $this->get(route('admin.right-to-work.export', $filters))->assertOk()
            ->assertDownload('right-to-work-status.csv')->streamedContent();
        $this->assertStringContainsString('Priya Sharma', $export);
        $this->assertStringContainsString("'=HYPERLINK", $export);
        $this->assertStringNotContainsString('Alex Morgan', $export);
    }

    public function test_employee_profile_lists_check_history(): void
    {
        RightToWorkCheck::create($this->payload(['check_date' => '2025-09-01', 'immigration_category' => 'Earlier check']));
        RightToWorkCheck::create($this->payload(['immigration_category' => 'Skilled Worker']));
        $this->get(route('admin.employees.show', ['employee' => $this->employee, 'tab' => 'rtw']))->assertOk()
            ->assertSee('id="employeeRightToWork"', false)
            ->assertSee('Skilled Worker')->assertSee('Earlier check')
            ->assertSee(route('admin.right-to-work.create', ['employee' => $this->employee->id]), false);
    }

    public function test_employees_and_other_roles_cannot_use_right_to_work(): void
    {
        $check = RightToWorkCheck::create($this->payload());
        Role::findOrCreate('auditor');
        $auditor = User::factory()->create();
        $auditor->assignRole('auditor');
        foreach ([$this->employee, $auditor] as $user) {
            $this->actingAs($user);
            $this->get(route('admin.right-to-work.index'))->assertForbidden();
            $this->get(route('admin.right-to-work.export'))->assertForbidden();
            $this->getJson(route('admin.right-to-work.show', $check))->assertForbidden();
            $this->postJson(route('admin.right-to-work.store'), $this->payload())->assertForbidden();
            $this->putJson(route('admin.right-to-work.update', $check), $this->payload())->assertForbidden();
            $this->deleteJson(route('admin.right-to-work.destroy', $check))->assertForbidden();
        }
    }

    private function payload(array $overrides = []): array
    {
        return array_replace([
            'employee_id' => $this->employee->id,
            'check_date' => '2026-09-09',
            'check_method' => 'Manual document check',
            'performed_by' => 'Compliance Administrator',
            'immigration_category' => 'Skilled Worker',
            'restrictions' => 'Sponsoring employer only',
            'permission_start' => '2023-09-04',
            'permission_expiry' => null,
            'follow_up_required' => false,
            'next_check_date' => null,
            'evidence_reference' => 'Passport verified original seen',
            'notes' => 'Standard onboarding check.',
        ], $overrides);
    }
}
