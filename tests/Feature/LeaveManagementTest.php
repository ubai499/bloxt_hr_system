<?php

namespace Tests\Feature;

use App\Models\LeaveRequest;
use App\Models\User;
use App\Services\LeaveBalance;
use Carbon\CarbonImmutable;
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

    public function test_admin_page_matches_the_prototype_controls_and_create_opens_the_modal(): void
    {
        $this->actingAs($this->admin())->get(route('admin.leave.index'))
            ->assertOk()->assertSee('id="leaveTable"', false)->assertSee('id="leaveForm"', false)
            ->assertSee('id="exportLeaveBtn"', false)->assertSee('admin-leave.js')
            ->assertDontSee('Awaiting a decision')->assertDontSee('breadcrumb-trail');
        $this->get(route('admin.leave.create'))->assertRedirect(route('admin.leave.index', ['new' => 1]));
    }

    public function test_partial_day_counts_as_half_a_day_and_notes_are_available_in_details(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-09-01'));
        $employee = $this->employee();
        $this->actingAs($this->admin())->postJson(route('admin.leave.store'), $this->requestData($employee, [
            'to_date' => '2026-09-14', 'partial_day' => true, 'notes' => 'Medical appointment',
        ]))->assertOk();
        $leave = LeaveRequest::firstOrFail();
        $this->patchJson(route('admin.leave.status.update', $leave), ['status' => 'Approved'])->assertOk();
        $this->getJson(route('admin.leave.show', $leave))->assertOk()
            ->assertJsonPath('duration', 0.5)->assertJsonPath('notes', 'Medical appointment')
            ->assertJsonPath('balances.0.booked', 0.5)->assertJsonPath('balances.0.remaining', 24.5);
    }

    public function test_dates_partial_day_and_weekend_ranges_are_validated(): void
    {
        $employee = $this->employee();
        $this->actingAs($this->admin());
        foreach ([
            [['to_date' => '2026-09-13'], 'to_date'],
            [['from_date' => 'not-a-date'], 'from_date'],
            [['partial_day' => true], 'to_date'],
            [['from_date' => '2026-09-12', 'to_date' => '2026-09-13'], 'from_date'],
        ] as [$changes, $error]) {
            $this->postJson(route('admin.leave.store'), $this->requestData($employee, $changes))
                ->assertUnprocessable()->assertJsonValidationErrors($error);
        }
        $this->assertDatabaseCount('leave_requests', 0);
    }

    public function test_pending_and_approved_requests_block_overlapping_dates(): void
    {
        $employee = $this->employee();
        $this->actingAs($this->admin());
        $this->postJson(route('admin.leave.store'), $this->requestData($employee))->assertOk();
        $this->postJson(route('admin.leave.store'), $this->requestData($employee, ['leave_type' => 'Sick leave']))
            ->assertUnprocessable()->assertJsonValidationErrors('from_date');
        $leave = LeaveRequest::firstOrFail();
        $this->patchJson(route('admin.leave.status.update', $leave), ['status' => 'Approved'])->assertOk();
        $this->postJson(route('admin.leave.store'), $this->requestData($employee))
            ->assertUnprocessable()->assertJsonValidationErrors('from_date');
    }

    public function test_allowance_is_checked_with_pending_reservations_and_again_on_approval(): void
    {
        $employee = $this->employee(['leave_allowance' => 5]);
        $this->actingAs($this->admin());
        $this->postJson(route('admin.leave.store'), $this->requestData($employee))->assertOk();
        $this->postJson(route('admin.leave.store'), $this->requestData($employee, ['from_date' => '2026-09-21', 'to_date' => '2026-09-21']))
            ->assertUnprocessable()->assertJsonValidationErrors('to_date');
        $employee->update(['leave_allowance' => 4]);
        $this->patchJson(route('admin.leave.status.update', LeaveRequest::firstOrFail()), ['status' => 'Approved'])
            ->assertUnprocessable()->assertJsonValidationErrors('to_date');
        $this->assertSame('Pending', LeaveRequest::firstOrFail()->status);
    }

    public function test_only_annual_leave_uses_allowance_and_past_days_become_taken(): void
    {
        $employee = $this->employee();
        $this->travelTo(CarbonImmutable::parse('2026-09-16'));
        $annual = LeaveRequest::create($this->requestData($employee) + ['status' => 'Approved']);
        LeaveRequest::create($this->requestData($employee, ['from_date' => '2026-09-21', 'to_date' => '2026-09-25', 'leave_type' => 'Sick leave']) + ['status' => 'Approved']);
        $balances = app(LeaveBalance::class);
        $this->assertEquals(['year' => 2026, 'allowance' => 25, 'taken' => 2, 'booked' => 3, 'remaining' => 20], $balances->balance($employee, 2026));
        $this->travelTo(CarbonImmutable::parse('2026-09-19'));
        $this->assertEquals(5, $balances->balance($employee, 2026)['taken']);
        $this->assertEquals(0, $balances->balance($employee, 2026)['booked']);
        $annual->update(['status' => 'Cancelled']);
        $this->assertEquals(25, $balances->balance($employee, 2026)['remaining']);
    }

    public function test_cross_year_requests_use_each_year_allowance_separately(): void
    {
        $employee = $this->employee(['leave_allowance' => 1]);
        $this->actingAs($this->admin())->postJson(route('admin.leave.store'), $this->requestData($employee, [
            'from_date' => '2026-12-31', 'to_date' => '2027-01-01',
        ]))->assertOk();
        $leave = LeaveRequest::firstOrFail();
        $this->patchJson(route('admin.leave.status.update', $leave), ['status' => 'Approved'])->assertOk();
        $this->getJson(route('admin.leave.show', $leave))->assertJsonCount(2, 'balances')
            ->assertJsonPath('balances.0.remaining', 0)->assertJsonPath('balances.1.remaining', 0);
    }

    public function test_self_approval_is_blocked_but_own_pending_leave_can_be_cancelled(): void
    {
        $admin = $this->admin();
        $admin->assignRole('employee');
        $leave = LeaveRequest::create($this->requestData($admin) + ['status' => 'Pending']);
        $this->actingAs($admin)->getJson(route('admin.leave.index'))->assertJsonPath('rows.0.can_approve', false)->assertJsonPath('rows.0.can_cancel', true);
        foreach (['Approved', 'Rejected'] as $status) {
            $this->patchJson(route('admin.leave.status.update', $leave), ['status' => $status])->assertForbidden();
        }
        $this->patchJson(route('admin.leave.status.update', $leave), ['status' => 'Cancelled'])->assertOk();
        $this->assertSame('Cancelled', $leave->fresh()->status);
    }

    public function test_decisions_are_final_and_cannot_be_repeated_edited_or_deleted(): void
    {
        $employee = $this->employee();
        $leave = LeaveRequest::create($this->requestData($employee) + ['status' => 'Pending']);
        $this->actingAs($this->admin());
        $this->patchJson(route('admin.leave.status.update', $leave), ['status' => 'Cancelled'])->assertForbidden();
        $this->patchJson(route('admin.leave.status.update', $leave), ['status' => 'Approved'])->assertOk();
        $this->patchJson(route('admin.leave.status.update', $leave), ['status' => 'Approved'])->assertUnprocessable();
        $this->putJson(route('admin.leave.update', $leave), $this->requestData($employee))->assertUnprocessable();
        $this->deleteJson(route('admin.leave.destroy', $leave))->assertUnprocessable();
        $this->assertSame('Approved', $leave->fresh()->status);
    }

    public function test_rejection_preserves_reason_and_releases_reserved_allowance(): void
    {
        $employee = $this->employee(['leave_allowance' => 5]);
        $leave = LeaveRequest::create($this->requestData($employee) + ['status' => 'Pending']);
        $this->actingAs($this->admin())->patchJson(route('admin.leave.status.update', $leave), ['status' => 'Rejected', 'rejection_reason' => 'Coverage needed'])
            ->assertOk();
        $this->getJson(route('admin.leave.show', $leave))->assertJsonPath('rejection_reason', 'Coverage needed');
        $this->postJson(route('admin.leave.store'), $this->requestData($employee))->assertOk();
    }

    public function test_filters_apply_to_rows_stats_and_export(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-09-14'));
        $employee = $this->employee();
        $other = $this->employee();
        LeaveRequest::create($this->requestData($employee, ['reason' => '=HYPERLINK("example")']) + ['status' => 'Approved']);
        LeaveRequest::create($this->requestData($other, ['reason' => 'Excluded reason']) + ['status' => 'Pending']);
        $this->actingAs($this->admin());
        $filters = ['status' => 'Approved', 'type' => 'Annual leave', 'employee' => $employee->id];
        $this->getJson(route('admin.leave.index', $filters))->assertJsonCount(1, 'rows')
            ->assertJsonPath('stats', ['pending' => 0, 'approved' => 1, 'on_leave_today' => 1, 'total' => 1]);
        $export = $this->get(route('admin.leave.export', $filters))->assertOk()->assertDownload('leave-requests.csv')->streamedContent();
        $this->assertStringContainsString("'=HYPERLINK", $export);
        $this->assertStringNotContainsString('Excluded reason', $export);
        $this->getJson(route('admin.leave.index', ['status' => 'Rejected']))->assertJsonCount(0, 'rows')->assertJsonPath('stats.total', 0);
    }

    public function test_left_employees_and_non_employees_cannot_receive_new_leave(): void
    {
        $admin = $this->admin();
        $left = $this->employee(['status' => 'Left']);
        $this->actingAs($admin);
        foreach ([$admin, $left] as $employee) {
            $this->postJson(route('admin.leave.store'), $this->requestData($employee))->assertUnprocessable()->assertJsonValidationErrors('employee_id');
        }
    }

    public function test_export_preserves_the_selected_table_sort_order(): void
    {
        $alpha = $this->employee(['name' => 'Alice Example']);
        $zulu = $this->employee(['name' => 'Zoe Example']);
        foreach ([$zulu, $alpha] as $employee) {
            LeaveRequest::create($this->requestData($employee) + ['status' => 'Pending']);
        }
        $csv = $this->actingAs($this->admin())->get(route('admin.leave.export', ['sort' => 'employee', 'direction' => 'asc']))
            ->assertOk()->streamedContent();
        $this->assertLessThan(strpos($csv, 'Zoe Example'), strpos($csv, 'Alice Example'));
    }

    public function test_a_single_day_cannot_be_booked_twice_and_invalid_input_is_preserved(): void
    {
        $employee = $this->employee();
        $data = $this->requestData($employee, ['to_date' => '2026-09-14', 'partial_day' => true]);
        $this->actingAs($this->admin())->postJson(route('admin.leave.store'), $data)->assertOk();
        $this->from(route('admin.leave.index'))->post(route('admin.leave.store'), $data)
            ->assertRedirect(route('admin.leave.index'))->assertSessionHasErrors('from_date')
            ->assertSessionHasInput('employee_id', $employee->id);
        $this->get(route('admin.leave.index'))->assertOk();
        $this->assertDatabaseCount('leave_requests', 1);
    }

    public function test_non_admin_roles_cannot_read_export_or_mutate_leave(): void
    {
        $employee = $this->employee();
        $leave = LeaveRequest::create($this->requestData($employee) + ['status' => 'Pending']);
        Role::findOrCreate('auditor');
        $auditor = User::factory()->create();
        $auditor->assignRole('auditor');
        foreach ([$employee, $auditor] as $user) {
            $this->actingAs($user);
            $this->getJson(route('admin.leave.index'))->assertForbidden();
            $this->getJson(route('admin.leave.show', $leave))->assertForbidden();
            $this->get(route('admin.leave.export'))->assertForbidden();
            $this->postJson(route('admin.leave.store'), $this->requestData($employee))->assertForbidden();
            $this->patchJson(route('admin.leave.status.update', $leave), ['status' => 'Approved'])->assertForbidden();
            $this->putJson(route('admin.leave.update', $leave), $this->requestData($employee))->assertForbidden();
            $this->deleteJson(route('admin.leave.destroy', $leave))->assertForbidden();
        }
    }

    private function admin(): User
    {
        $user = User::factory()->create(['status' => 'Active']);
        $user->assignRole('admin');

        return $user;
    }

    private function employee(array $attributes = []): User
    {
        $user = User::factory()->create($attributes + ['status' => 'Active']);
        $user->assignRole('employee');

        return $user->refresh();
    }

    private function requestData(User $employee, array $changes = []): array
    {
        return $changes + ['employee_id' => $employee->id, 'leave_type' => 'Annual leave', 'from_date' => '2026-09-14', 'to_date' => '2026-09-18', 'partial_day' => false];
    }
}
