<?php

namespace Tests\Feature;

use App\Models\AttendanceRecord;
use App\Models\Department;
use App\Models\LeaveRequest;
use App\Models\SponsorshipRecord;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class AdminDashboardTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $employee;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(CarbonImmutable::parse('2026-09-11 10:00:00'));
        Role::findOrCreate('admin');
        Role::findOrCreate('employee');
        $this->admin = User::factory()->create(['name' => 'Amina Khan']);
        $this->admin->assignRole('admin');
        $department = Department::create(['name' => 'Engineering', 'status' => 'Active']);
        $this->employee = User::factory()->create([
            'name' => 'Priya Sharma',
            'status' => 'Active',
            'job_title' => 'Software Developer',
            'department_id' => $department->id,
            'probation_end_date' => '2026-10-01',
        ]);
        $this->employee->assignRole('employee');
        $this->actingAs($this->admin);
    }

    public function test_dashboard_uses_live_records_instead_of_demo_copy(): void
    {
        $this->get(route('admin.dashboard'))->assertOk()
            ->assertSee('Good morning, Amina')
            ->assertSee('Total Employees')
            ->assertSee('Action Required')
            ->assertSee('Upcoming Compliance Events')
            ->assertSee('Recent Activity')
            ->assertSee('Priya Sharma')
            ->assertSee('Probation review')
            ->assertSee('Employee created')
            ->assertSee('View all tasks')
            ->assertSee('Full calendar')
            ->assertDontSee('Visa expiry review')
            ->assertDontSee('Prepare payroll handoff')
            ->assertDontSee('assets/js/storage.js')
            ->assertDontSee('HR.db');
        $html = $this->get(route('admin.dashboard'))->getContent();
        $this->assertStringContainsString('Engineering', $html);
        $this->assertMatchesRegularExpression('/Total Employees<\/span>\s*<span class="metric-value">1<\/span>/', $html);
        $this->assertDoesNotMatchRegularExpression('/Documents Expiring Soon<\/span>\s*<span class="metric-value">14<\/span>/', $html);
        $this->assertDoesNotMatchRegularExpression('/Outstanding HR Actions<\/span>\s*<span class="metric-value">7<\/span>/', $html);
    }

    public function test_kpis_and_charts_update_when_records_change(): void
    {
        AttendanceRecord::create([
            'employee_id' => $this->employee->id,
            'date' => '2026-09-11',
            'status' => 'Sick',
            'manager_reviewed' => false,
        ]);
        LeaveRequest::create([
            'employee_id' => $this->employee->id,
            'leave_type' => 'Annual leave',
            'from_date' => '2026-09-11',
            'to_date' => '2026-09-12',
            'status' => 'Approved',
            'requested_at' => now(),
        ]);
        SponsorshipRecord::create([
            'employee_id' => $this->employee->id,
            'worker_route' => 'Skilled Worker',
            'sponsorship_status' => 'Current',
        ]);
        $html = $this->get(route('admin.dashboard'))->assertOk()->getContent();
        $this->assertMatchesRegularExpression('/Absent Today<\/span>\s*<span class="metric-value">1<\/span>/', $html);
        $this->assertMatchesRegularExpression('/On Leave Today<\/span>\s*<span class="metric-value">1<\/span>/', $html);
        $this->assertMatchesRegularExpression('/Sponsored Workers<\/span>\s*<span class="metric-value">1<\/span>/', $html);
        $this->assertStringContainsString('Sick', $html);
        $this->assertStringContainsString('Employee details incomplete', $html);
    }

    public function test_employees_cannot_open_the_admin_dashboard(): void
    {
        $this->actingAs($this->employee)
            ->get(route('admin.dashboard'))
            ->assertForbidden();
    }
}
