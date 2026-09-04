<?php

namespace Database\Seeders;

use App\Models\Department;
use App\Models\RightToWorkCheck;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        Role::findOrCreate('admin');
        Role::findOrCreate('employee');
        $operations = Department::firstOrCreate(['name' => 'Operations'], ['status' => 'Active']);

        $admin = User::updateOrCreate(['email' => 'admin@admin.com'], [
            'name' => 'HR Administrator',
            'password' => Hash::make('password'),
        ]);
        $admin->syncRoles('admin');

        $employee = User::updateOrCreate(['email' => 'employee@employee.com'], [
            'name' => 'Alex Morgan',
            'password' => Hash::make('password'),
            'employee_number' => 'BXT-001',
            'job_title' => 'Operations Coordinator',
            'department_id' => $operations->id,
            'employment_type' => 'Full-time',
            'work_location' => 'London',
            'start_date' => '2025-09-01',
            'phone' => '020 7946 0100',
            'status' => 'Active',
        ]);
        $employee->syncRoles('employee');

        RightToWorkCheck::updateOrCreate([
            'employee_id' => $employee->id,
            'check_date' => '2025-09-01',
        ], [
            'check_method' => 'Manual document check',
            'performed_by' => 'HR Administrator',
            'immigration_category' => 'British Citizen',
            'status' => 'Valid',
            'evidence_reference' => 'On file',
        ]);
    }
}
