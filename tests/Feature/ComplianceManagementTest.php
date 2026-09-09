<?php

namespace Tests\Feature;

use App\Models\ComplianceReview;
use App\Models\Department;
use App\Models\RightToWorkCheck;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ComplianceManagementTest extends TestCase
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
            'name' => 'Elena Novak',
            'status' => 'Active',
            'department_id' => $department->id,
            'end_date' => '2026-12-01',
            'probation_end_date' => '2026-10-01',
        ]);
        $this->employee->assignRole('employee');
        $this->actingAs($this->admin);
    }

    public function test_page_matches_the_prototype_and_create_opens_reviews(): void
    {
        $this->get(route('admin.compliance.index', ['tab' => 'calendar']))->assertOk()
            ->assertSee('Compliance')->assertSee('Compliance Calendar')
            ->assertSee('id="calendarBody"', false)->assertSee('admin-compliance.js')
            ->assertSee('does not replace the Home Office Sponsor Management System')
            ->assertDontSee('assets/js/storage.js');
        $this->get(route('admin.compliance.create'))
            ->assertRedirect(route('admin.compliance.index', ['tab' => 'reviews', 'new' => 1]));
    }

    public function test_metrics_calendar_and_checklist_are_derived_from_live_records(): void
    {
        RightToWorkCheck::create([
            'employee_id' => $this->employee->id,
            'check_date' => '2026-09-01',
            'check_method' => 'Online Home Office check',
            'immigration_category' => 'EU Settlement Scheme Pre-Settled Status',
            'permission_expiry' => '2026-10-20',
            'follow_up_required' => true,
            'next_check_date' => '2026-10-20',
            'status' => 'Review Due',
        ]);
        $payload = $this->getJson(route('admin.compliance.index'))->assertOk()->json();
        $this->assertGreaterThan(0, $payload['metrics']['rtw_due']);
        $this->assertGreaterThan(0, $payload['metrics']['immigration_near_expiry']);
        $this->assertGreaterThan(0, $payload['metrics']['action_required']);
        $this->assertNotEmpty($payload['events']);
        $this->assertTrue(collect($payload['events'])->contains(fn ($event) => $event['type'] === 'Right-to-work follow-up'));
        $this->assertTrue(collect($payload['events'])->contains(fn ($event) => $event['type'] === 'Probation review'));
        $checklist = collect($payload['employees'])->firstWhere('name', 'Elena Novak')['checklist'];
        $this->assertFalse(collect($checklist)->firstWhere('label', 'Personal details complete')['complete']);
        $this->assertTrue(collect($checklist)->firstWhere('label', 'Right-to-work record on file')['complete']);
    }

    public function test_an_internal_review_can_be_recorded_updated_and_removed(): void
    {
        $this->postJson(route('admin.compliance.store'), $this->reviewPayload(['review_number' => '']))
            ->assertUnprocessable()->assertJsonValidationErrors('review_number');
        $this->postJson(route('admin.compliance.store'), $this->reviewPayload())->assertOk();
        $review = ComplianceReview::first();
        $this->assertNull($review->completion_date);
        $this->putJson(route('admin.compliance.reviews.update', $review), $this->reviewPayload([
            'result' => 'Completed',
            'issues_found' => 'None remaining.',
        ]))->assertOk();
        $this->assertNotNull($review->fresh()->completion_date);
        $this->getJson(route('admin.compliance.reviews.show', $review))->assertOk()
            ->assertJsonPath('review_number', 'REV-2026-Q3');
        $this->deleteJson(route('admin.compliance.reviews.destroy', $review))->assertOk();
        $this->assertDatabaseCount('compliance_reviews', 0);
    }

    public function test_overdue_reviews_are_counted(): void
    {
        ComplianceReview::create($this->reviewPayload(['due_date' => '2026-08-01', 'result' => 'Follow-up Required']));
        $this->getJson(route('admin.compliance.index'))->assertJsonPath('metrics.overdue_reviews', 1);
    }

    public function test_employees_cannot_use_compliance(): void
    {
        $review = ComplianceReview::create($this->reviewPayload());
        $this->actingAs($this->employee);
        $this->get(route('admin.compliance.index'))->assertForbidden();
        $this->postJson(route('admin.compliance.store'), $this->reviewPayload(['review_number' => 'REV-OTHER']))->assertForbidden();
        $this->deleteJson(route('admin.compliance.reviews.destroy', $review))->assertForbidden();
    }

    private function reviewPayload(array $overrides = []): array
    {
        return array_replace([
            'review_number' => 'REV-2026-Q3',
            'review_date' => '2026-09-01',
            'reviewer' => 'Compliance Administrator',
            'area' => 'Right to work records',
            'employees_sampled' => 3,
            'records_reviewed' => 'Right-to-work checks',
            'issues_found' => 'One follow-up still outstanding.',
            'actions_required' => 'Complete the follow-up check.',
            'responsible_person' => 'HR Administrator',
            'due_date' => '2026-10-01',
            'result' => 'Follow-up Required',
        ], $overrides);
    }
}
