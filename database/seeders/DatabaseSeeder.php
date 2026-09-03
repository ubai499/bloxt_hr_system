<?php

namespace Database\Seeders;

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

        $admin = User::updateOrCreate(['email' => 'admin@bloxt.test'], [
            'name' => 'HR Administrator',
            'password' => Hash::make('password'),
        ]);
        $admin->syncRoles('admin');

        $employee = User::updateOrCreate(['email' => 'employee@bloxt.test'], [
            'name' => 'Alex Morgan',
            'password' => Hash::make('password'),
        ]);
        $employee->syncRoles('employee');
    }
}
