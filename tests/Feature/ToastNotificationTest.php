<?php

namespace Tests\Feature;

use App\Models\Department;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ToastNotificationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Role::findOrCreate('admin');
        Role::findOrCreate('employee');
        $admin = User::factory()->create();
        $admin->assignRole('admin');
        $this->actingAs($admin);
    }

    public function test_department_mutations_display_shared_toasts_once_and_reads_stay_quiet(): void
    {
        $this->get(route('admin.departments.index'))->assertOk()->assertDontSee('HR.toast({', false);

        $this->post(route('admin.departments.store'), ['name' => 'Finance', 'status' => 'Active'])
            ->assertSessionHas('success', 'Department created successfully.')
            ->assertSessionHas('toast_title', 'Department created');
        $department = Department::where('name', 'Finance')->firstOrFail();
        $page = $this->get(route('admin.departments.show', $department))->assertOk();
        $page->assertSee('HR.toast({', false)->assertSee('Department created successfully.')
            ->assertDontSee('class="alert alert-success"', false);
        $this->assertSame(1, substr_count($page->getContent(), 'assets/js/notifications.js'));
        $this->get(route('admin.departments.show', $department))->assertDontSee('HR.toast({', false);

        $this->put(route('admin.departments.update', $department), ['name' => 'Operations', 'status' => 'Active'])
            ->assertSessionHas('toast_title', 'Department updated');
        $this->get(route('admin.departments.show', $department))->assertSee('Department updated successfully.');

        $this->delete(route('admin.departments.destroy', $department))->assertSessionHas('toast_title', 'Department deleted');
        $this->get(route('admin.departments.index'))->assertSee('Department deleted successfully.');
        $this->get(route('admin.departments.index'))->assertDontSee('HR.toast({', false);
    }

    public function test_failed_mutations_do_not_display_success_and_guard_errors_use_shared_toast(): void
    {
        $this->post(route('admin.departments.store'), ['name' => '', 'status' => 'Active'])
            ->assertSessionHasErrors('name')->assertSessionMissing('success');

        $department = Department::create(['name' => 'Operations', 'status' => 'Active']);
        $employee = User::factory()->create(['department_id' => $department->id]);
        $employee->assignRole('employee');
        $this->delete(route('admin.departments.destroy', $department))
            ->assertSessionHas('error')->assertSessionMissing('success');
        $this->get(route('admin.departments.show', $department))
            ->assertSee('Move employees to another department before deleting this department.')
            ->assertSee("type: 'danger'", false)->assertDontSee('class="alert alert-danger"', false);
    }

    public function test_ajax_save_does_not_queue_duplicate_redirect_notification(): void
    {
        $employee = User::factory()->create(['status' => 'Active']);
        $employee->assignRole('employee');
        $this->postJson(route('admin.attendance.store'), [
            'employee_id' => $employee->id,
            'date' => '2026-09-04',
            'status' => 'Present',
            'clock_in' => '09:00',
            'clock_out' => '17:00',
            'hours' => 8,
        ])->assertSuccessful()->assertJsonPath('message', 'The attendance record has been added.')
            ->assertSessionMissing('success');
        $this->get(route('admin.attendance.index'))->assertOk()->assertDontSee('HR.toast({', false);
    }

    public function test_flash_messages_are_serialized_safely(): void
    {
        $this->withSession(['success' => '</script><img src=x onerror=alert(1)>'])
            ->get(route('admin.departments.index'))->assertOk()
            ->assertSee('HR.toast({', false)->assertDontSee('</script><img src=x', false);
    }
}
