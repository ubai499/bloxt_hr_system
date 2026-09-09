<?php

namespace Tests\Feature;

use App\Models\Candidate;
use App\Models\Department;
use App\Models\User;
use App\Models\Vacancy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class RecruitmentManagementTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private Department $department;

    protected function setUp(): void
    {
        parent::setUp();
        Role::findOrCreate('admin');
        Role::findOrCreate('employee');
        $this->admin = User::factory()->create(['name' => 'Recruitment Administrator']);
        $this->admin->assignRole('admin');
        $this->department = Department::create(['name' => 'Engineering', 'status' => 'Active']);
        $this->actingAs($this->admin);
    }

    public function test_page_matches_the_prototype_and_create_opens_the_modal(): void
    {
        $this->get(route('admin.recruitment.index'))->assertOk()
            ->assertSee('Recruitment')->assertSee('New Vacancy')->assertSee('id="vacancyList"', false)
            ->assertSee('id="vacancyForm"', false)->assertSee('id="candidateForm"', false)
            ->assertSee('id="recruitmentDetailsModal"', false)->assertSee('id="recruitmentDetailsEdit"', false)
            ->assertSee('Recruitment channel')->assertSee('admin-recruitment.js')
            ->assertDontSee('assets/js/storage.js')->assertDontSee('HR.db')
            ->assertSee('This system records the process actually followed');
        $this->get(route('admin.recruitment.create'))->assertRedirect(route('admin.recruitment.index', ['new' => 1]));
    }

    public function test_admin_can_create_a_vacancy_with_server_owned_open_status(): void
    {
        $this->postJson(route('admin.recruitment.store'), $this->vacancyPayload(['status' => 'Filled']))
            ->assertOk()->assertJsonPath('message', 'The new vacancy has been added.');
        $vacancy = Vacancy::firstOrFail();
        $this->assertSame('Software Developer', $vacancy->job_title);
        $this->assertSame('Open', $vacancy->status);
        $this->assertSame($this->department->id, $vacancy->department_id);
        $this->assertEquals(36000, $vacancy->salary_range_min);
        $this->getJson(route('admin.recruitment.index'))->assertOk()
            ->assertJsonPath('stats.open', 1)->assertJsonCount(1, 'vacancies')
            ->assertJsonPath('vacancies.0.job_title', 'Software Developer')
            ->assertJsonPath('vacancies.0.accepts_candidates', true);
    }

    public function test_vacancy_dates_and_salary_range_are_validated(): void
    {
        foreach ([
            [['job_title' => ''], 'job_title'],
            [['closing_date' => '2026-06-01'], 'closing_date'],
            [['salary_range_max' => 10000], 'salary_range_max'],
            [['employment_type' => 'Full-time'], 'employment_type'],
        ] as [$changes, $error]) {
            $this->postJson(route('admin.recruitment.store'), $this->vacancyPayload($changes))
                ->assertUnprocessable()->assertJsonValidationErrors($error);
        }
        $this->assertDatabaseCount('vacancies', 0);
    }

    public function test_admin_can_add_and_update_a_candidate(): void
    {
        $vacancy = $this->vacancy();
        $this->postJson(route('admin.recruitment.candidates.store', $vacancy), $this->candidatePayload([
            'name' => '=HYPERLINK("example")',
        ]))->assertOk();
        $candidate = Candidate::firstOrFail();
        $this->assertSame('In Progress', $candidate->outcome);
        $this->putJson(route('admin.recruitment.candidates.update', [$vacancy, $candidate]), $this->candidatePayload([
            'name' => 'Alex Turner', 'outcome' => 'Offered', 'interview_records' => 'Second interview booked.',
        ]))->assertOk();
        $this->assertSame('Offered', $candidate->fresh()->outcome);
        $this->getJson(route('admin.recruitment.index'))->assertJsonPath('stats.offered', 1)
            ->assertJsonPath('vacancies.0.candidates.0.name', 'Alex Turner');
    }

    public function test_closed_and_filled_vacancies_cannot_receive_candidates(): void
    {
        foreach (['Closed', 'Filled'] as $status) {
            $vacancy = $this->vacancy(['status' => $status]);
            $this->postJson(route('admin.recruitment.candidates.store', $vacancy), $this->candidatePayload())
                ->assertUnprocessable()->assertJsonValidationErrors('vacancy_id');
        }
        $this->assertDatabaseCount('candidates', 0);
    }

    public function test_on_hold_vacancies_can_still_receive_candidates(): void
    {
        $vacancy = $this->vacancy(['status' => 'On Hold']);
        $this->postJson(route('admin.recruitment.candidates.store', $vacancy), $this->candidatePayload())->assertOk();
        $this->assertDatabaseCount('candidates', 1);
    }

    public function test_vacancy_can_be_updated_and_status_changed(): void
    {
        $vacancy = $this->vacancy();
        $this->putJson(route('admin.recruitment.update', $vacancy), $this->vacancyPayload([
            'job_title' => 'Senior Software Developer', 'status' => 'On Hold', 'salary_range_max' => 52000,
        ]))->assertOk();
        $this->assertSame('Senior Software Developer', $vacancy->fresh()->job_title);
        $this->assertSame('On Hold', $vacancy->fresh()->status);
        $this->patchJson(route('admin.recruitment.status.update', $vacancy), ['status' => 'Filled'])->assertOk();
        $this->assertSame('Filled', $vacancy->fresh()->status);
        $this->assertFalse($vacancy->fresh()->acceptsCandidates());
    }

    public function test_deleting_a_vacancy_removes_its_candidates(): void
    {
        $vacancy = $this->vacancy();
        $this->candidate($vacancy);
        $this->deleteJson(route('admin.recruitment.destroy', $vacancy))->assertOk();
        $this->assertDatabaseCount('vacancies', 0);
        $this->assertDatabaseCount('candidates', 0);
    }

    public function test_candidate_routes_are_scoped_to_the_vacancy(): void
    {
        $vacancy = $this->vacancy();
        $other = $this->vacancy(['job_title' => 'Finance Manager']);
        $candidate = $this->candidate($vacancy);
        $this->putJson(route('admin.recruitment.candidates.update', [$other, $candidate]), $this->candidatePayload())
            ->assertNotFound();
        $this->deleteJson(route('admin.recruitment.candidates.destroy', [$other, $candidate]))->assertNotFound();
        $this->assertDatabaseHas('candidates', ['id' => $candidate->id, 'vacancy_id' => $vacancy->id]);
        $this->deleteJson(route('admin.recruitment.candidates.destroy', [$vacancy, $candidate]))->assertOk();
        $this->assertDatabaseCount('candidates', 0);
    }

    public function test_filters_apply_to_rows_stats_and_export(): void
    {
        $otherDept = Department::create(['name' => 'Finance', 'status' => 'Active']);
        $kept = $this->vacancy();
        $this->candidate($kept, ['name' => '=HYPERLINK("example")', 'outcome' => 'Hired']);
        $this->candidate($this->vacancy(['job_title' => 'Finance Manager', 'department_id' => $otherDept->id]), [
            'name' => 'Excluded candidate', 'outcome' => 'In Progress',
        ]);
        $filters = ['status' => 'Open', 'department' => $this->department->id, 'search' => 'Software'];
        $this->getJson(route('admin.recruitment.index', $filters))->assertJsonCount(1, 'vacancies')
            ->assertJsonPath('stats', ['open' => 1, 'in_progress' => 0, 'offered' => 0, 'hired' => 1]);
        $export = $this->get(route('admin.recruitment.export', $filters))->assertOk()->assertDownload('recruitment.csv')->streamedContent();
        $this->assertStringContainsString("'=HYPERLINK", $export);
        $this->assertStringContainsString('Software Developer', $export);
        $this->assertStringNotContainsString('Excluded candidate', $export);
        $this->assertStringNotContainsString('Finance Manager', $export);
    }

    public function test_export_includes_vacancies_that_have_no_candidates(): void
    {
        $this->vacancy(['job_title' => 'Operations Assistant']);
        $export = $this->get(route('admin.recruitment.export'))->assertOk()->streamedContent();
        $this->assertStringContainsString('Operations Assistant', $export);
    }

    public function test_invalid_vacancy_input_is_preserved_on_redirect(): void
    {
        $this->from(route('admin.recruitment.index'))->post(route('admin.recruitment.store'), $this->vacancyPayload([
            'closing_date' => '2026-06-01',
        ]))->assertRedirect(route('admin.recruitment.index'))->assertSessionHasErrors('closing_date')
            ->assertSessionHasInput('job_title', 'Software Developer');
        $this->get(route('admin.recruitment.index'))->assertOk()->assertSee('value="Software Developer"', false);
    }

    public function test_employees_and_other_roles_cannot_use_recruitment(): void
    {
        $vacancy = $this->vacancy();
        $candidate = $this->candidate($vacancy);
        Role::findOrCreate('auditor');
        $employee = User::factory()->create();
        $employee->assignRole('employee');
        $auditor = User::factory()->create();
        $auditor->assignRole('auditor');
        foreach ([$employee, $auditor] as $user) {
            $this->actingAs($user);
            $this->get(route('admin.recruitment.index'))->assertForbidden();
            $this->get(route('admin.recruitment.export'))->assertForbidden();
            $this->postJson(route('admin.recruitment.store'), $this->vacancyPayload())->assertForbidden();
            $this->putJson(route('admin.recruitment.update', $vacancy), $this->vacancyPayload())->assertForbidden();
            $this->patchJson(route('admin.recruitment.status.update', $vacancy), ['status' => 'Closed'])->assertForbidden();
            $this->postJson(route('admin.recruitment.candidates.store', $vacancy), $this->candidatePayload())->assertForbidden();
            $this->deleteJson(route('admin.recruitment.destroy', $vacancy))->assertForbidden();
            $this->deleteJson(route('admin.recruitment.candidates.destroy', [$vacancy, $candidate]))->assertForbidden();
        }
        $this->actingAs($employee)->get(route('employee.dashboard'))->assertOk()->assertDontSee('href="'.route('admin.recruitment.index').'"', false);
    }

    private function vacancy(array $overrides = []): Vacancy
    {
        return Vacancy::create($this->vacancyPayload($overrides));
    }

    private function candidate(Vacancy $vacancy, array $overrides = []): Candidate
    {
        return $vacancy->candidates()->create($this->candidatePayload($overrides));
    }

    private function vacancyPayload(array $overrides = []): array
    {
        return array_replace([
            'job_title' => 'Software Developer',
            'department_id' => $this->department->id,
            'hiring_manager' => 'David Chen',
            'employment_type' => 'Permanent',
            'opening_date' => '2026-07-01',
            'closing_date' => '2026-09-23',
            'salary_range_min' => 36000,
            'salary_range_max' => 46000,
            'location' => 'Bloxt House (Hybrid)',
            'recruitment_channel' => 'Company careers page',
            'reason_for_vacancy' => 'Team growth',
            'status' => 'Open',
        ], $overrides);
    }

    private function candidatePayload(array $overrides = []): array
    {
        return array_replace([
            'name' => 'Alex Turner',
            'application_date' => '2026-08-05',
            'source' => 'Company careers page',
            'interview_records' => 'First-stage interview scheduled 08 Sep 2026.',
            'outcome' => 'In Progress',
        ], $overrides);
    }
}
