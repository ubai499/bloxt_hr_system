<?php

namespace Tests\Feature;

use App\Models\CompanyChange;
use App\Models\Department;
use App\Models\GuidanceReference;
use App\Models\SponsorEvent;
use App\Models\SponsorLicence;
use App\Models\SponsorshipRecord;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class SponsorshipManagementTest extends TestCase
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
        $department = Department::create(['name' => 'Engineering', 'status' => 'Active']);
        $this->employee = User::factory()->create([
            'name' => 'Priya Sharma',
            'status' => 'Active',
            'job_title' => 'Software Developer',
            'department_id' => $department->id,
            'address' => '1 Test Street',
            'postcode' => 'LS1 1AA',
            'phone' => '07123456789',
            'emergency_contact_name' => 'Parent',
            'emergency_contact_phone' => '07987654321',
            'contact_verified_date' => '2026-08-01',
        ]);
        $this->employee->assignRole('employee');
        $this->actingAs($this->admin);
    }

    public function test_page_matches_the_prototype_and_create_opens_the_workspace(): void
    {
        $this->get(route('admin.sponsorship.index'))->assertOk()
            ->assertSee('Sponsor Compliance Workspace')
            ->assertSee('Sponsored Workers')->assertSee('Reporting Register')
            ->assertSee('does not replace the Home Office Sponsor Management System')
            ->assertSee('admin-sponsorship.js')
            ->assertDontSee('assets/js/storage.js');
        $this->get(route('admin.sponsorship.create', ['tab' => 'events', 'type' => 'event']))
            ->assertRedirect(route('admin.sponsorship.index', ['tab' => 'events', 'new' => 'event']));
    }

    public function test_a_sponsored_worker_and_event_can_be_recorded(): void
    {
        $this->postJson(route('admin.sponsorship.workers.store'), $this->workerPayload(['employee_id' => $this->admin->id]))
            ->assertUnprocessable()->assertJsonValidationErrors('employee_id');
        $this->postJson(route('admin.sponsorship.workers.store'), $this->workerPayload())->assertOk();
        $this->postJson(route('admin.sponsorship.workers.store'), $this->workerPayload())
            ->assertUnprocessable()->assertJsonValidationErrors('employee_id');
        $this->postJson(route('admin.sponsorship.events.store'), $this->eventPayload())->assertOk();
        $this->getJson(route('admin.sponsorship.index'))->assertOk()
            ->assertJsonPath('stats.sponsored', 1)
            ->assertJsonCount(1, 'workers')
            ->assertJsonCount(1, 'events')
            ->assertJsonPath('workers.0.employee', 'Priya Sharma');
    }

    public function test_events_require_a_current_sponsored_worker(): void
    {
        $this->postJson(route('admin.sponsorship.events.store'), $this->eventPayload())
            ->assertUnprocessable()->assertJsonValidationErrors('employee_id');
    }

    public function test_licence_company_change_and_guidance_can_be_updated(): void
    {
        $this->putJson(route('admin.sponsorship.licence.update'), [
            'status' => 'Valid',
            'reference' => 'SPN-1000-2026',
            'rating' => 'A-Rating',
            'worker_routes' => 'Skilled Worker, Graduate',
            'sms_url' => 'https://www.gov.uk/sponsor-management-system',
        ])->assertOk();
        $this->assertSame('SPN-1000-2026', SponsorLicence::current()->reference);
        $this->assertSame(['Skilled Worker', 'Graduate'], SponsorLicence::current()->worker_routes);

        $this->postJson(route('admin.sponsorship.changes.store'), [
            'change_type' => 'Registered office change',
            'date' => '2026-09-01',
            'description' => 'Office moved within Leeds.',
            'report_required' => true,
            'reported' => true,
            'reported_date' => '2026-09-02',
        ])->assertOk();
        $this->postJson(route('admin.sponsorship.guidance.store'), [
            'title' => 'Skilled Worker guidance',
            'source' => 'GOV.UK',
            'url' => 'https://www.gov.uk/skilled-worker-visa',
        ])->assertOk();
        $this->assertDatabaseCount('company_changes', 1);
        $this->assertTrue(GuidanceReference::query()->where('title', 'Skilled Worker guidance')->exists());
    }

    public function test_records_can_be_updated_and_removed(): void
    {
        $record = SponsorshipRecord::create($this->workerPayload());
        $event = SponsorEvent::create($this->eventPayload());
        $this->putJson(route('admin.sponsorship.workers.update', $record), $this->workerPayload([
            'employee_id' => $this->admin->id,
            'soc_code' => '2134',
        ]))->assertOk();
        $this->assertSame($this->employee->id, $record->fresh()->employee_id);
        $this->assertSame('2134', $record->fresh()->soc_code);
        $this->putJson(route('admin.sponsorship.events.update', $event), $this->eventPayload([
            'status' => 'Not Reportable',
            'details' => 'Assessed as not reportable.',
        ]))->assertOk();
        $this->deleteJson(route('admin.sponsorship.events.destroy', $event))->assertOk();
        $this->deleteJson(route('admin.sponsorship.workers.destroy', $record))->assertOk();
        $this->assertDatabaseCount('sponsorship_records', 0);
        $this->assertDatabaseCount('sponsor_events', 0);
    }

    public function test_employee_profile_shows_the_sponsorship_record(): void
    {
        SponsorshipRecord::create($this->workerPayload(['cos_reference' => 'C2H9F8K21X']));
        $this->get(route('admin.employees.show', ['employee' => $this->employee, 'tab' => 'sponsorship']))->assertOk()
            ->assertSee('id="employeeSponsorship"', false)
            ->assertSee('C2H9F8K21X')
            ->assertSee('Open Sponsor Compliance Workspace');
    }

    public function test_employees_cannot_use_sponsor_compliance(): void
    {
        $record = SponsorshipRecord::create($this->workerPayload());
        $this->actingAs($this->employee);
        $this->get(route('admin.sponsorship.index'))->assertForbidden();
        $this->postJson(route('admin.sponsorship.workers.store'), $this->workerPayload())->assertForbidden();
        $this->deleteJson(route('admin.sponsorship.workers.destroy', $record))->assertForbidden();
    }

    private function workerPayload(array $overrides = []): array
    {
        return array_replace([
            'employee_id' => $this->employee->id,
            'worker_route' => 'Skilled Worker',
            'sponsor_licence_ref' => 'SPN-8842-2024',
            'cos_reference' => 'C2H9F8K21X',
            'sponsorship_status' => 'Current',
            'hr_responsible_person' => 'Compliance Administrator',
        ], $overrides);
    }

    private function eventPayload(array $overrides = []): array
    {
        return array_replace([
            'employee_id' => $this->employee->id,
            'event_type' => 'Salary change',
            'date_occurred' => '2026-08-15',
            'date_aware' => '2026-08-15',
            'details' => 'Annual salary review pending sign-off.',
            'assigned_to' => 'Compliance Administrator',
            'status' => 'Requires Review',
        ], $overrides);
    }
}
