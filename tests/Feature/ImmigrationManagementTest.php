<?php

namespace Tests\Feature;

use App\Models\Department;
use App\Models\RightToWorkCheck;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ImmigrationManagementTest extends TestCase
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
        $this->get(route('admin.immigration.index'))->assertOk()
            ->assertSee('Immigration Records')->assertSee('Record Immigration Permission')
            ->assertSee('id="immTable"', false)->assertSee('never inferred from nationality')
            ->assertSee('admin-immigration.js')
            ->assertDontSee('assets/js/storage.js')->assertDontSee('HR.db');
        $this->get(route('admin.immigration.create', ['employee' => $this->employee->id]))
            ->assertRedirect(route('admin.immigration.index', ['new' => 1, 'employee' => $this->employee->id]));
    }

    public function test_only_recorded_permissions_are_listed_and_new_entries_are_additive(): void
    {
        $this->getJson(route('admin.immigration.index'))->assertOk()->assertJsonCount(0, 'rows');
        $this->postJson(route('admin.immigration.store'), $this->payload(['employee_id' => $this->admin->id]))
            ->assertUnprocessable()->assertJsonValidationErrors('employee_id');
        $this->postJson(route('admin.immigration.store'), $this->payload(['check_date' => '2026-08-15']))->assertOk();
        $this->postJson(route('admin.immigration.store'), $this->payload([
            'immigration_category' => 'Skilled Worker (updated)',
            'permission_expiry' => '2027-09-03',
            'follow_up_required' => false,
            'next_check_date' => null,
        ]))->assertOk();
        $this->assertDatabaseCount('right_to_work_checks', 2);
        $this->getJson(route('admin.immigration.index'))->assertOk()
            ->assertJsonCount(1, 'rows')
            ->assertJsonPath('rows.0.immigration_category', 'Skilled Worker (updated)')
            ->assertJsonPath('rows.0.status', 'Valid');
    }

    public function test_status_is_derived_from_permission_dates(): void
    {
        RightToWorkCheck::create($this->payload(['permission_expiry' => '2026-08-01']));
        $this->getJson(route('admin.immigration.index'))->assertJsonPath('rows.0.status', 'Expired')
            ->assertJsonPath('stats.expired', 1);
    }

    public function test_category_and_dates_are_validated(): void
    {
        foreach ([
            [['immigration_category' => ''], 'immigration_category'],
            [['permission_start' => '2026-09-01', 'permission_expiry' => '2026-08-01'], 'permission_expiry'],
            [['follow_up_required' => '1', 'next_check_date' => null], 'next_check_date'],
        ] as [$changes, $error]) {
            $this->postJson(route('admin.immigration.store'), $this->payload($changes))
                ->assertUnprocessable()->assertJsonValidationErrors($error);
        }
    }

    public function test_a_permission_can_be_updated_and_export_escapes_formulas(): void
    {
        $check = RightToWorkCheck::create($this->payload(['restrictions' => '=HYPERLINK("example")']));
        $this->putJson(route('admin.immigration.update', $check), $this->payload([
            'employee_id' => $this->admin->id,
            'immigration_category' => 'British Citizen',
            'permission_expiry' => null,
            'restrictions' => '=HYPERLINK("example")',
        ]))->assertOk();
        $this->assertSame($this->employee->id, $check->fresh()->employee_id);
        $this->assertSame('British Citizen', $check->fresh()->immigration_category);
        $export = $this->get(route('admin.immigration.export'))->assertOk()
            ->assertDownload('immigration-records.csv')->streamedContent();
        $this->assertStringContainsString('Priya Sharma', $export);
        $this->assertStringContainsString("'=HYPERLINK", $export);
        $this->deleteJson(route('admin.immigration.destroy', $check))->assertOk();
        $this->assertDatabaseCount('right_to_work_checks', 0);
    }

    public function test_employees_cannot_use_immigration_records(): void
    {
        $check = RightToWorkCheck::create($this->payload());
        $this->actingAs($this->employee);
        $this->get(route('admin.immigration.index'))->assertForbidden();
        $this->postJson(route('admin.immigration.store'), $this->payload())->assertForbidden();
        $this->deleteJson(route('admin.immigration.destroy', $check))->assertForbidden();
    }

    private function payload(array $overrides = []): array
    {
        return array_replace([
            'employee_id' => $this->employee->id,
            'check_date' => '2026-09-09',
            'check_method' => 'Online Home Office check',
            'performed_by' => 'Compliance Administrator',
            'immigration_category' => 'Skilled Worker',
            'restrictions' => 'Sponsoring employer only',
            'permission_start' => '2023-09-04',
            'permission_expiry' => '2026-09-03',
            'follow_up_required' => true,
            'next_check_date' => '2026-09-03',
        ], $overrides);
    }
}
