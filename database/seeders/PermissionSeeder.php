<?php

namespace Database\Seeders;

use App\Models\Permission;
use Illuminate\Database\Seeder;

class PermissionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $modules = [
            'users',
            'roles',
            'permissions',
            'employees',
            'departments',
            'positions',
            'attendance',
            'payroll',
            'leaves',
            'evaluations',
        ];

        $actions = ['view', 'create', 'update', 'delete'];

        foreach ($modules as $module) {
            foreach ($actions as $action) {
                Permission::firstOrCreate([
                    'name' => "{$action} {$module}",
                    'guard_name' => 'web',
                ]);
            }
        }

        // Add management permissions for departments and positions
        Permission::firstOrCreate([
            'name' => 'manage departments',
            'guard_name' => 'web',
        ]);

        Permission::firstOrCreate([
            'name' => 'manage positions',
            'guard_name' => 'web',
        ]);

        // Add management permission for attendance
        Permission::firstOrCreate([
            'name' => 'manage attendance',
            'guard_name' => 'web',
        ]);

        Permission::firstOrCreate([
            'name' => 'manage leaves',
            'guard_name' => 'web',
        ]);

        Permission::firstOrCreate([
            'name' => 'manage evaluations',
            'guard_name' => 'web',
        ]);
    }
}
