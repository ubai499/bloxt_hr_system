<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class AttendanceManagementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Role::findOrCreate('admin');
        Role::findOrCreate('employee');
    }

    public function test_admin_can_record_attendance_and_absence(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        $employee = User::factory()->create(['status' => 'Active']);
        $employee->assignRole('employee');

        $attendanceResponse = $this->actingAs($admin)->post(route('admin.attendance.store'), [
            'employee_id' => $employee->id,
            'date' => '2026-09-04',
            'expected_start' => '09:00',
            'clock_in' => '08:55',
            'clock_out' => '17:30',
            'hours' => 8,
            'work_location' => 'Office',
            'status' => 'Present',
            'manager_reviewed' => '1',
        ]);

        $attendanceResponse->assertRedirect(route('admin.attendance.index'));
        $this->assertDatabaseHas('attendance_records', [
            'employee_id' => $employee->id,
            'status' => 'Present',
            'manager_reviewed' => true,
        ]);

        $absenceResponse = $this->actingAs($admin)->post(route('admin.attendance.absence.store'), [
            'employee_id' => $employee->id,
            'date' => '2026-09-05',
            'absence_type' => 'Sick',
            'reason' => 'Flu-like symptoms',
            'reported_date' => '2026-09-05',
            'authorised' => '1',
        ]);

        $absenceResponse->assertRedirect(route('admin.attendance.index', ['tab' => 'absence']));
        $this->assertDatabaseHas('absence_records', [
            'employee_id' => $employee->id,
            'absence_type' => 'Sick',
            'authorised' => true,
        ]);
    }

    public function test_employee_cannot_access_admin_attendance(): void
    {
        $employee = User::factory()->create();
        $employee->assignRole('employee');

        $this->actingAs($employee)
            ->get(route('admin.attendance.index'))
            ->assertForbidden();
    }
}
