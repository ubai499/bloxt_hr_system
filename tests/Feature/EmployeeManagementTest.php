<?php

namespace Tests\Feature;

use App\Models\Department;
use App\Models\RightToWorkCheck;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class EmployeeManagementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Role::findOrCreate('admin');
        Role::findOrCreate('employee');
    }

    public function test_admin_is_redirected_to_the_admin_dashboard(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        $this->actingAs($admin)
            ->get(route('home'))
            ->assertRedirect(route('admin.dashboard'));
    }

    public function test_employee_can_open_their_dashboard_but_not_employee_management(): void
    {
        $employee = User::factory()->create();
        $employee->assignRole('employee');

        $this->actingAs($employee)
            ->get(route('employee.dashboard'))
            ->assertOk()
            ->assertSee('Employee workspace');

        $this->actingAs($employee)
            ->get(route('admin.employees.index'))
            ->assertForbidden();
    }

    public function test_admin_can_create_an_employee_with_login_access(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');
        $department = Department::create(['name' => 'People Operations', 'status' => 'Active']);

        $response = $this->actingAs($admin)->post(route('admin.employees.store'), [
            'name' => 'Taylor Jordan',
            'email' => 'taylor@example.com',
            'phone' => '07123456789',
            'job_title' => 'People Coordinator',
            'department_id' => $department->id,
            'employment_type' => 'Full-time',
            'work_location' => 'London',
            'start_date' => '2026-09-04',
            'status' => 'Active',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $employee = User::where('email', 'taylor@example.com')->firstOrFail();

        $response->assertRedirect(route('admin.employees.show', $employee));
        $this->assertTrue($employee->hasRole('employee'));
        $this->assertDatabaseHas('users', [
            'email' => 'taylor@example.com',
            'employee_number' => 'BXT-001',
            'department_id' => $department->id,
        ]);
    }

    public function test_employee_ids_are_assigned_sequentially(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');
        $department = Department::create(['name' => 'People Operations', 'status' => 'Active']);

        $this->actingAs($admin)
            ->get(route('admin.employees.create'))
            ->assertOk()
            ->assertSee('BXT-001')
            ->assertSee('Assigned automatically.');

        $first = $this->actingAs($admin)->post(route('admin.employees.store'), $this->employeePayload($department, [
            'name' => 'First Hire',
            'email' => 'first@example.com',
        ]));
        $second = $this->actingAs($admin)->post(route('admin.employees.store'), $this->employeePayload($department, [
            'name' => 'Second Hire',
            'email' => 'second@example.com',
            'employee_number' => 'BXT-999',
        ]));

        $firstEmployee = User::where('email', 'first@example.com')->firstOrFail();
        $secondEmployee = User::where('email', 'second@example.com')->firstOrFail();

        $first->assertRedirect(route('admin.employees.show', $firstEmployee));
        $second->assertRedirect(route('admin.employees.show', $secondEmployee));
        $this->assertSame('BXT-001', $firstEmployee->employee_number);
        $this->assertSame('BXT-002', $secondEmployee->employee_number);
    }

    public function test_next_employee_id_follows_the_highest_existing_number(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');
        $department = Department::create(['name' => 'People Operations', 'status' => 'Active']);
        User::factory()->create(['employee_number' => 'BXT-009'])->assignRole('employee');

        $this->actingAs($admin)
            ->get(route('admin.employees.create'))
            ->assertOk()
            ->assertSee('BXT-010');

        $this->actingAs($admin)->post(route('admin.employees.store'), $this->employeePayload($department, [
            'name' => 'Next Hire',
            'email' => 'next@example.com',
        ]));

        $this->assertDatabaseHas('users', [
            'email' => 'next@example.com',
            'employee_number' => 'BXT-010',
        ]);
    }

    public function test_employee_id_cannot_be_changed_when_updating(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');
        $department = Department::create(['name' => 'People Operations', 'status' => 'Active']);
        $employee = User::factory()->create([
            'name' => 'Taylor Jordan',
            'email' => 'taylor@example.com',
            'employee_number' => 'BXT-001',
            'job_title' => 'People Coordinator',
            'department_id' => $department->id,
            'employment_type' => 'Full-time',
            'status' => 'Active',
        ]);
        $employee->assignRole('employee');

        $this->actingAs($admin)->put(route('admin.employees.update', $employee), [
            'employee_number' => 'BXT-500',
            'name' => 'Taylor Jordan',
            'email' => 'taylor@example.com',
            'job_title' => 'People Coordinator',
            'department_id' => $department->id,
            'employment_type' => 'Full-time',
            'status' => 'Active',
        ])->assertRedirect(route('admin.employees.show', $employee));

        $this->assertSame('BXT-001', $employee->fresh()->employee_number);
    }

    public function test_admin_can_create_a_department(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        $response = $this->actingAs($admin)->post(route('admin.departments.store'), [
            'name' => 'Finance',
            'status' => 'Active',
        ]);

        $department = Department::where('name', 'Finance')->firstOrFail();

        $response->assertRedirect(route('admin.departments.show', $department));
        $this->assertDatabaseHas('departments', ['name' => 'Finance', 'status' => 'Active']);
    }

    public function test_employee_directory_derives_sponsorship_from_the_latest_right_to_work_check(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');
        $employee = User::factory()->create(['name' => 'Sponsored Employee']);
        $employee->assignRole('employee');

        RightToWorkCheck::create([
            'employee_id' => $employee->id,
            'check_date' => now()->subDay(),
            'check_method' => 'Online Home Office check',
            'permission_expiry' => now()->addYear(),
            'status' => 'Valid',
        ]);

        $this->actingAs($admin)
            ->get(route('admin.employees.index'))
            ->assertOk()
            ->assertSee('Sponsored')
            ->assertSee('data-sponsored="1"', false);
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function employeePayload(Department $department, array $overrides = []): array
    {
        return array_merge([
            'name' => 'Taylor Jordan',
            'email' => 'taylor@example.com',
            'phone' => '07123456789',
            'job_title' => 'People Coordinator',
            'department_id' => $department->id,
            'employment_type' => 'Full-time',
            'work_location' => 'London',
            'start_date' => '2026-09-04',
            'status' => 'Active',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ], $overrides);
    }
}
