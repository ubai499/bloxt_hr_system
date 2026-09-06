<?php

namespace Tests\Feature;

use App\Models\AbsenceRecord;
use App\Models\AttendanceRecord;
use App\Models\LeaveRequest;
use App\Models\RightToWorkCheck;
use App\Models\User;
use App\Services\AttendanceAlerts;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class AbsenceManagementTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $employee;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(CarbonImmutable::parse('2026-09-18 12:00:00'));
        Role::findOrCreate('admin');
        Role::findOrCreate('employee');
        $this->admin = User::factory()->create(['status' => 'Active']);
        $this->admin->assignRole('admin');
        $this->employee = User::factory()->create(['status' => 'Active']);
        $this->employee->assignRole('employee');
        $this->actingAs($this->admin);
    }

    public function test_workspace_uses_prototype_panels_columns_and_modals_without_demo_storage(): void
    {
        $this->get(route('admin.attendance.index', ['tab' => 'absence']))->assertOk()
            ->assertSee('Review Alerts')->assertSee('Reported')->assertSee('Follow-up')
            ->assertSee('id="absenceTable"', false)->assertSee('id="absForm"', false)
            ->assertSee('id="attForm"', false)->assertSee('admin-attendance.js')
            ->assertDontSee('assets/js/storage.js')->assertDontSee('HR.db');
        $this->get(route('admin.attendance.absence.create'))->assertRedirect(route('admin.attendance.index', ['tab' => 'absence', 'new' => 1]));
    }

    public function test_recording_absence_creates_matching_attendance_and_persists_all_form_fields(): void
    {
        $this->postJson(route('admin.attendance.absence.store'), $this->data([
            'reason' => 'Flu', 'reported_date' => '2026-09-15', 'how_reported' => 'Phone call', 'reported_to' => 'Line manager',
            'expected_return' => '2026-09-18', 'manager_notes' => 'Contact tomorrow', 'authorised' => true, 'follow_up_required' => true,
        ]))->assertOk();
        $absence = AbsenceRecord::firstOrFail();
        $attendance = $absence->attendanceRecord;
        $this->assertSame('Sick', $attendance->status);
        $this->assertFalse($attendance->manager_reviewed);
        $this->assertEquals(0, $attendance->hours);
        $this->assertTrue($absence->attendance_created);
        $this->getJson(route('admin.attendance.absence.show', $absence))->assertJsonPath('manager_notes', 'Contact tomorrow')
            ->assertJsonPath('reported_date', '2026-09-15')->assertJsonPath('reported_to', 'Line manager')->assertJsonPath('authorised', true);
    }

    public function test_no_reporting_information_stays_unreported(): void
    {
        $this->postJson(route('admin.attendance.absence.store'), $this->data())->assertOk();
        $this->assertNull(AbsenceRecord::firstOrFail()->reported_date);
        $this->getJson(route('admin.attendance.index'))->assertJsonPath('absences.0.reported_date', null);
    }

    public function test_return_reporting_and_authorisation_validation_reject_bad_records(): void
    {
        foreach ([
            [['expected_return' => '2026-09-14'], 'expected_return'],
            [['actual_return' => '2026-09-14'], 'actual_return'],
            [['actual_return' => '2026-09-19'], 'actual_return'],
            [['reported_date' => '2026-09-19'], 'reported_date'],
            [['how_reported' => 'Email'], 'reported_date'],
            [['absence_type' => 'Authorised Absence', 'authorised' => false], 'authorised'],
            [['absence_type' => 'Unauthorised Absence', 'authorised' => true], 'authorised'],
        ] as [$changes, $field]) {
            $this->postJson(route('admin.attendance.absence.store'), $this->data($changes))->assertUnprocessable()->assertJsonValidationErrors($field);
        }
        $this->assertDatabaseCount('absence_records', 0);
        $this->assertDatabaseCount('attendance_records', 0);
    }

    public function test_duplicate_absence_and_conflicting_attendance_do_not_overwrite_records(): void
    {
        $this->postJson(route('admin.attendance.absence.store'), $this->data())->assertOk();
        $this->postJson(route('admin.attendance.absence.store'), $this->data())->assertUnprocessable()->assertJsonValidationErrors('date');
        AttendanceRecord::create(['employee_id' => $this->employee->id, 'date' => '2026-09-16', 'status' => 'Present', 'hours' => 8]);
        $this->postJson(route('admin.attendance.absence.store'), $this->data(['date' => '2026-09-16']))->assertUnprocessable()->assertJsonValidationErrors('date');
        $this->assertDatabaseCount('absence_records', 1);
        $this->assertDatabaseCount('attendance_records', 2);
        $this->assertDatabaseHas('attendance_records', ['status' => 'Present', 'hours' => 8]);
    }

    public function test_existing_matching_attendance_is_reused_and_retained_when_absence_is_deleted(): void
    {
        $attendance = AttendanceRecord::create(['employee_id' => $this->employee->id, 'date' => '2026-09-15', 'status' => 'Sick', 'hours' => 0]);
        $this->postJson(route('admin.attendance.absence.store'), $this->data())->assertOk();
        $absence = AbsenceRecord::firstOrFail();
        $this->assertSame($attendance->id, $absence->attendance_record_id);
        $this->assertFalse($absence->attendance_created);
        $this->delete(route('admin.attendance.absence.destroy', $absence))->assertRedirect();
        $this->assertDatabaseCount('attendance_records', 1);
    }

    public function test_review_records_actual_return_and_resolves_follow_up_and_alerts(): void
    {
        $this->postJson(route('admin.attendance.absence.store'), $this->data(['absence_type' => 'Unauthorised Absence', 'follow_up_required' => true]))->assertOk();
        $absence = AbsenceRecord::firstOrFail();
        $this->assertNotEmpty(app(AttendanceAlerts::class)->all());
        $this->putJson(route('admin.attendance.absence.update', $absence), $this->data([
            'absence_type' => 'Authorised Absence', 'authorised' => true, 'follow_up_required' => false,
            'actual_return' => '2026-09-17', 'manager_notes' => 'Returned and reviewed',
        ]))->assertOk();
        $absence->refresh();
        $this->assertSame('2026-09-17', $absence->actual_return->toDateString());
        $this->assertSame($this->admin->name, $absence->reviewed_by);
        $this->assertSame('Authorised Absence', $absence->attendanceRecord->status);
        $this->assertTrue($absence->attendanceRecord->manager_reviewed);
        $this->getJson(route('admin.attendance.index'))->assertJsonCount(0, 'alerts');
    }

    public function test_moving_or_deleting_absence_cleans_up_its_generated_attendance(): void
    {
        $this->postJson(route('admin.attendance.absence.store'), $this->data())->assertOk();
        $absence = AbsenceRecord::firstOrFail();
        $oldId = $absence->attendance_record_id;
        $this->putJson(route('admin.attendance.absence.update', $absence), $this->data(['date' => '2026-09-16']))->assertOk();
        $this->assertDatabaseMissing('attendance_records', ['id' => $oldId]);
        $this->assertDatabaseCount('attendance_records', 1);
        $this->delete(route('admin.attendance.absence.destroy', $absence))->assertRedirect();
        $this->assertDatabaseCount('attendance_records', 0);
    }

    public function test_attendance_cannot_be_changed_to_conflict_with_its_absence(): void
    {
        $this->postJson(route('admin.attendance.absence.store'), $this->data())->assertOk();
        $record = AttendanceRecord::firstOrFail();
        $this->putJson(route('admin.attendance.update', $record), ['employee_id' => $this->employee->id, 'date' => '2026-09-15', 'status' => 'Present', 'hours' => 8])
            ->assertUnprocessable()->assertJsonValidationErrors('status');
        $this->deleteJson(route('admin.attendance.destroy', $record))->assertUnprocessable();
        $this->assertDatabaseCount('attendance_records', 1);
    }

    public function test_attendance_review_refreshes_alerts_but_does_not_silently_resolve_absence_follow_up(): void
    {
        $this->postJson(route('admin.attendance.absence.store'), $this->data(['absence_type' => 'Unauthorised Absence', 'follow_up_required' => true]))->assertOk();
        $record = AttendanceRecord::firstOrFail();
        $this->patchJson(route('admin.attendance.review', $record))->assertOk();
        $this->getJson(route('admin.attendance.index'))->assertJsonCount(1, 'alerts')->assertJsonPath('alerts.0.type', 'Absence record requires follow-up');
    }

    public function test_sponsored_follow_up_alert_is_not_hidden_by_missing_attendance_history(): void
    {
        RightToWorkCheck::create(['employee_id' => $this->employee->id, 'check_date' => '2026-01-01', 'check_method' => 'Online Home Office check', 'permission_expiry' => '2027-01-01', 'performed_by' => 'HR']);
        AbsenceRecord::create($this->data(['follow_up_required' => true]));
        $types = array_column(app(AttendanceAlerts::class)->all(), 'type');
        $this->assertContains('No attendance recorded', $types);
        $this->assertContains('Sponsored worker has an unresolved absence record', $types);
    }

    public function test_reviewed_historical_absences_do_not_escalate_a_single_outstanding_absence(): void
    {
        foreach ([['2025-01-01', true], ['2026-09-15', false]] as [$date, $reviewed]) {
            AttendanceRecord::create(['employee_id' => $this->employee->id, 'date' => $date, 'status' => 'Unauthorised Absence', 'manager_reviewed' => $reviewed]);
        }
        $types = array_column(app(AttendanceAlerts::class)->all(), 'type');
        $this->assertContains('Employee has missed expected working day', $types);
        $this->assertNotContains('Repeated unexplained absence', $types);
    }

    public function test_leave_and_absence_cannot_be_approved_for_the_same_day_in_either_order(): void
    {
        $leave = LeaveRequest::create(['employee_id' => $this->employee->id, 'leave_type' => 'Annual leave', 'from_date' => '2026-09-15', 'to_date' => '2026-09-15', 'status' => 'Approved']);
        $this->postJson(route('admin.attendance.absence.store'), $this->data())->assertUnprocessable()->assertJsonValidationErrors('date');
        $leave->update(['status' => 'Pending']);
        $this->postJson(route('admin.attendance.absence.store'), $this->data())->assertOk();
        $this->patchJson(route('admin.leave.status.update', $leave), ['status' => 'Approved'])->assertUnprocessable()->assertJsonValidationErrors('from_date');
    }

    public function test_left_employee_cannot_receive_new_absence_but_existing_follow_up_can_be_reviewed(): void
    {
        $this->postJson(route('admin.attendance.absence.store'), $this->data(['follow_up_required' => true]))->assertOk();
        $absence = AbsenceRecord::firstOrFail();
        $this->employee->update(['status' => 'Left']);
        $this->postJson(route('admin.attendance.absence.store'), $this->data(['date' => '2026-09-16']))->assertUnprocessable()->assertJsonValidationErrors('employee_id');
        $this->putJson(route('admin.attendance.absence.update', $absence), $this->data(['follow_up_required' => false]))->assertOk();
    }

    public function test_non_admin_roles_cannot_view_create_review_or_delete_absences(): void
    {
        $absence = AbsenceRecord::create($this->data());
        Role::findOrCreate('auditor');
        $auditor = User::factory()->create();
        $auditor->assignRole('auditor');
        foreach ([$auditor, $this->employee] as $user) {
            $this->actingAs($user)->getJson(route('admin.attendance.index'))->assertForbidden();
            $this->getJson(route('admin.attendance.absence.show', $absence))->assertForbidden();
            $this->postJson(route('admin.attendance.absence.store'), $this->data())->assertForbidden();
            $this->putJson(route('admin.attendance.absence.update', $absence), $this->data())->assertForbidden();
            $this->deleteJson(route('admin.attendance.absence.destroy', $absence))->assertForbidden();
            $this->get(route('admin.attendance.export'))->assertForbidden();
        }
    }

    public function test_attendance_export_filters_and_escapes_spreadsheet_formulas(): void
    {
        AttendanceRecord::create(['employee_id' => $this->employee->id, 'date' => '2026-09-15', 'status' => 'Present', 'notes' => '=1+1']);
        AttendanceRecord::create(['employee_id' => $this->employee->id, 'date' => '2026-09-16', 'status' => 'Remote', 'notes' => 'Excluded']);
        $csv = $this->get(route('admin.attendance.export', ['date' => '2026-09-15', 'status' => 'Present']))->assertDownload('attendance-records.csv')->streamedContent();
        $this->assertStringContainsString("'=1+1", $csv);
        $this->assertStringNotContainsString('Excluded', $csv);
    }

    private function data(array $changes = []): array
    {
        return $changes + ['employee_id' => $this->employee->id, 'date' => '2026-09-15', 'absence_type' => 'Sick', 'authorised' => false, 'follow_up_required' => false];
    }
}
