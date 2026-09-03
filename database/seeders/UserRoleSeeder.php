<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;

class UserRoleSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $admin = User::firstOrCreate(
            ['email' => 'admin@example.com'],
            [
                'first_name' => 'Admin',
                'last_name' => 'User',
                'name' => 'Admin User',
                'password' => bcrypt('password'),
                'is_active' => true,
            ]
        );

        $rhManager = User::firstOrCreate(
            ['email' => 'rh@example.com'],
            [
                'first_name' => 'RH',
                'last_name' => 'Manager',
                'name' => 'RH Manager',
                'password' => bcrypt('password'),
                'is_active' => true,
            ]
        );

        $manager = User::firstOrCreate(
            ['email' => 'manager@example.com'],
            [
                'first_name' => 'Manager',
                'last_name' => 'User',
                'name' => 'Manager User',
                'password' => bcrypt('password'),
                'is_active' => true,
            ]
        );

        $employee = User::firstOrCreate(
            ['email' => 'employee@example.com'],
            [
                'first_name' => 'Employee',
                'last_name' => 'User',
                'name' => 'Employee User',
                'password' => bcrypt('password'),
                'is_active' => true,
            ]
        );

        $adminRole = Role::findByName('admin', 'web');
        $rhManagerRole = Role::findByName('rh_manager', 'web');
        $managerRole = Role::findByName('manager', 'web');
        $employeeRole = Role::findByName('employee', 'web');

        // Assign roles
        $admin->assignRole($adminRole);
        $rhManager->assignRole($rhManagerRole);
        $manager->assignRole($managerRole);
        $employee->assignRole($employeeRole);

        // Assign all permissions to admin role
        $allPermissions = Permission::all();
        $adminRole->syncPermissions($allPermissions);

        // Assign specific permissions to other roles
        $rhPermissions = Permission::wherein('name', [
            'view employees',
            'create employees',
            'update employees',
            'delete employees',
            'view departments',
            'view positions',
        ])->get();
        $rhManagerRole->syncPermissions($rhPermissions);
    }
}
