<?php

namespace Tests\Feature;

use App\Models\AuditEvent;
use App\Models\Department;
use App\Models\HrTask;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class AuditLogManagementTest extends TestCase
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
        $this->admin = User::factory()->create(['name' => 'Audit Administrator']);
        $this->admin->assignRole('admin');
        $department = Department::create(['name' => 'Operations', 'status' => 'Active']);
        $this->employee = User::factory()->create([
            'name' => 'Elena Novak',
            'status' => 'Active',
            'department_id' => $department->id,
        ]);
        $this->employee->assignRole('employee');
        $this->actingAs($this->admin);
    }

    public function test_page_matches_the_prototype(): void
    {
        $this->get(route('admin.audit-log.index'))->assertOk()
            ->assertSee('Audit Log')->assertSee('cannot be edited or removed')
            ->assertSee('id="auditTable"', false)->assertSee('admin-audit.js')
            ->assertDontSee('assets/js/storage.js')->assertDontSee('HR.db');
    }

    public function test_significant_actions_are_recorded_automatically(): void
    {
        HrTask::create([
            'title' => 'Prepare licence review',
            'employee_id' => $this->employee->id,
            'category' => 'Sponsorship',
            'assigned_to' => 'Audit Administrator',
            'priority' => 'High',
            'status' => 'Open',
        ]);
        $this->getJson(route('admin.audit-log.index'))->assertOk()
            ->assertJsonFragment(['action' => 'HR task created', 'module' => 'Tasks', 'employee' => 'Elena Novak'])
            ->assertJsonFragment(['action' => 'Department created', 'module' => 'Departments']);
    }

    public function test_events_cannot_be_changed_or_removed(): void
    {
        $event = AuditEvent::create([
            'occurred_at' => now(),
            'user_name' => 'System',
            'action' => 'Seeded event',
            'module' => 'Compliance',
            'description' => '=CMD()',
        ]);
        $this->expectException(RuntimeException::class);
        $event->update(['action' => 'Changed']);
    }

    public function test_export_and_filters_work_and_escape_formulas(): void
    {
        AuditEvent::create([
            'occurred_at' => now(),
            'user_name' => 'System',
            'action' => 'Seeded event',
            'module' => 'Compliance',
            'description' => '=CMD()',
        ]);
        $this->getJson(route('admin.audit-log.index', ['module' => 'Compliance']))->assertOk()
            ->assertJsonPath('rows.0.module', 'Compliance');
        $this->getJson(route('admin.audit-log.index', ['module' => 'Leave']))->assertOk()->assertJsonCount(0, 'rows');
        $export = $this->get(route('admin.audit-log.export', ['module' => 'Compliance']))->assertOk()
            ->assertDownload('audit-log.csv')->streamedContent();
        $this->assertStringContainsString('Seeded event', $export);
        $this->assertStringContainsString("'=CMD()", $export);
    }

    public function test_employee_profile_shows_activity(): void
    {
        $this->get(route('admin.employees.show', ['employee' => $this->employee, 'tab' => 'activity']))->assertOk()
            ->assertSee('id="employeeActivity"', false)
            ->assertSee('Employee created')
            ->assertSee('Open Audit Log');
    }

    public function test_employees_cannot_view_the_audit_log(): void
    {
        $this->actingAs($this->employee);
        $this->get(route('admin.audit-log.index'))->assertForbidden();
        $this->get(route('admin.audit-log.export'))->assertForbidden();
    }
}
