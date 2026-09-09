<?php

namespace Tests\Feature;

use App\Models\Department;
use App\Models\HrNotification;
use App\Models\HrTask;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ReportsManagementTest extends TestCase
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
        $this->admin = User::factory()->create(['name' => 'Reports Administrator']);
        $this->admin->assignRole('admin');
        $department = Department::create(['name' => 'Operations', 'status' => 'Active']);
        $this->employee = User::factory()->create([
            'name' => 'Priya Sharma',
            'status' => 'Active',
            'employee_number' => 'BXT-220',
            'job_title' => 'Software Developer',
            'department_id' => $department->id,
        ]);
        $this->employee->assignRole('employee');
        $this->actingAs($this->admin);
    }

    public function test_page_matches_the_prototype_and_create_opens_tasks(): void
    {
        $this->get(route('admin.reports.index'))->assertOk()
            ->assertSee('Reports')->assertSee('Report Catalogue')->assertSee('HR Tasks')
            ->assertSee('Employee Directory')->assertSee('id="tasksTable"', false)
            ->assertSee('admin-reports.js')
            ->assertDontSee('assets/js/storage.js')->assertDontSee('HR.db');
        $this->get(route('admin.reports.create', ['employee' => $this->employee->id]))
            ->assertRedirect(route('admin.reports.index', ['tab' => 'tasks', 'new' => 1, 'employee' => $this->employee->id]));
    }

    public function test_live_reports_can_be_opened_and_exported(): void
    {
        $report = $this->getJson(route('admin.reports.show', 'directory'))->assertOk()->json();
        $this->assertSame('Employee Directory', $report['title']);
        $this->assertSame(['Employee ID', 'Name', 'Job Title', 'Department', 'Status', 'Start Date'], $report['columns']);
        $this->assertTrue(collect($report['rows'])->contains(fn ($row) => $row[0] === 'BXT-220' && $row[1] === 'Priya Sharma' && $row[4] === 'Active'));
        $this->getJson(route('admin.reports.show', 'missing-report'))->assertNotFound();
        $export = $this->get(route('admin.reports.export', 'directory'))->assertOk()
            ->assertDownload('directory.csv')->streamedContent();
        $this->assertStringContainsString('Priya Sharma', $export);
        $this->assertStringContainsString('Employee ID', $export);
    }

    public function test_report_export_escapes_formulas(): void
    {
        $this->employee->update(['name' => '=HYPERLINK("example")']);
        $export = $this->get(route('admin.reports.export', 'directory'))->assertOk()->streamedContent();
        $this->assertStringContainsString("'=HYPERLINK", $export);
    }

    public function test_an_hr_task_can_be_created_updated_and_resolved_from_notifications(): void
    {
        $this->postJson(route('admin.reports.tasks.store'), $this->taskPayload(['title' => '']))
            ->assertUnprocessable()->assertJsonValidationErrors('title');
        $this->postJson(route('admin.reports.tasks.store'), $this->taskPayload(['employee_id' => $this->admin->id]))
            ->assertUnprocessable()->assertJsonValidationErrors('employee_id');
        $this->postJson(route('admin.reports.tasks.store'), $this->taskPayload())->assertOk();
        $task = HrTask::first();
        $this->assertSame('Review right-to-work evidence', $task->title);
        $this->assertSame('General', $task->category);
        $this->getJson(route('admin.reports.index'))->assertOk()
            ->assertJsonPath('tasks.0.employee', 'Priya Sharma')
            ->assertJsonCount(1, 'notifications');
        $this->putJson(route('admin.reports.tasks.update', $task), $this->taskPayload([
            'title' => 'Review passport copy',
            'priority' => 'High',
        ]))->assertOk();
        $this->assertSame('Review passport copy', $task->fresh()->title);
        $this->patchJson(route('admin.reports.tasks.status', $task), ['status' => 'Completed'])->assertOk();
        $this->assertSame('Completed', $task->fresh()->status);
        $notification = HrNotification::first();
        $this->patchJson(route('admin.reports.notifications.resolve', $notification))->assertOk();
        $this->assertSame('Resolved', $notification->fresh()->status);
    }

    public function test_employees_cannot_use_reports(): void
    {
        $task = HrTask::create($this->taskPayload());
        $this->actingAs($this->employee);
        $this->get(route('admin.reports.index'))->assertForbidden();
        $this->postJson(route('admin.reports.tasks.store'), $this->taskPayload(['title' => 'Other']))->assertForbidden();
        $this->patchJson(route('admin.reports.tasks.status', $task), ['status' => 'Completed'])->assertForbidden();
    }

    private function taskPayload(array $overrides = []): array
    {
        return array_replace([
            'title' => 'Review right-to-work evidence',
            'employee_id' => $this->employee->id,
            'category' => '',
            'assigned_to' => 'Reports Administrator',
            'priority' => 'Medium',
            'due_date' => '2026-09-20',
            'status' => 'Open',
            'description' => 'Check the latest evidence pack.',
        ], $overrides);
    }
}
