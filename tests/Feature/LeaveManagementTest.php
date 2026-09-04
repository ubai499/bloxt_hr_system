<?php

namespace Tests\Feature;

use App\Models\LeaveRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class LeaveManagementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Role::findOrCreate('admin');
        Role::findOrCreate('employee');
    }

    public function test_admin_can_submit_and_approve_a_leave_request(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        $employee = User::factory()->create(['status' => 'Active']);
        $employee->assignRole('employee');

        $response = $this->actingAs($admin)->post(route('admin.leave.store'), [
            'employee_id' => $employee->id,
            'leave_type' => 'Annual leave',
            'from_date' => '2026-09-14',
            'to_date' => '2026-09-18',
            'reason' => 'Family visit',
        ]);

        $response->assertRedirect(route('admin.leave.index'));
        $leaveRequest = LeaveRequest::firstOrFail();
        $this->assertSame('Pending', $leaveRequest->status);

        $this->actingAs($admin)
            ->patch(route('admin.leave.status.update', $leaveRequest), ['status' => 'Approved'])
            ->assertRedirect();

        $leaveRequest->refresh();
        $this->assertSame('Approved', $leaveRequest->status);
        $this->assertSame($admin->name, $leaveRequest->approved_by);
    }

    public function test_employee_cannot_access_admin_leave(): void
    {
        $employee = User::factory()->create();
        $employee->assignRole('employee');

        $this->actingAs($employee)
            ->get(route('admin.leave.index'))
            ->assertForbidden();
    }
}
