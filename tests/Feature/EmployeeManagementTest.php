<?php

namespace Tests\Feature;

use App\Models\Department;
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
            'employee_number' => 'BXT-001',
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
}
