<?php

namespace Database\Seeders;

use App\Modules\Auth\Models\User;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RolePermissionSeeder extends Seeder
{
    public function run(): void
    {
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        $permissions = [
            'view companies',
            'create companies',
            'edit companies',
            'delete companies',
            'view branches',
            'create branches',
            'edit branches',
            'delete branches',
            'view departments',
            'create departments',
            'edit departments',
            'delete departments',
            'view positions',
            'create positions',
            'edit positions',
            'delete positions',
            'view salary_grades',
            'create salary_grades',
            'edit salary_grades',
            'delete salary_grades',
            'view employees',
            'create employees',
            'edit employees',
            'delete employees',
            'import employees',
            'export employees',
            'terminate employees',
            'view attendances',
            'import attendances',
            'edit attendances',
            'manage overtime',
            'approve overtime',
            'view payroll',
            'generate payroll',
            'lock payroll',
            'close payroll',
            'export payroll',
            'print payslip',
            'view leave',
            'manage leave',
            'approve leave',
            'manage leave types',
            'manage leave settings',
            'approve requests',
            'reject requests',
            'view supervisor dashboard',
            'manage supervisor data',
            'export supervisor data',
        ];

        foreach ($permissions as $permission) {
            Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'web']);
        }

        $superadmin = Role::firstOrCreate(['name' => 'superadmin', 'guard_name' => 'web']);
        $hrmanager = Role::firstOrCreate(['name' => 'hrmanager', 'guard_name' => 'web']);
        $admManager = Role::firstOrCreate(['name' => 'adm_manager', 'guard_name' => 'web']);
        $hrbranch = Role::firstOrCreate(['name' => 'hrbranch', 'guard_name' => 'web']);
        $hrAst = Role::firstOrCreate(['name' => 'hr_ast', 'guard_name' => 'web']);

        $superadmin->syncPermissions(Permission::all());

        $hrmanager->syncPermissions([
            'view companies',
            'view branches',
            'view departments',
            'view positions',
            'view salary_grades',
            'view employees',
            'create employees',
            'edit employees',
            'delete employees',
            'import employees',
            'export employees',
            'terminate employees',
            'view attendances',
            'import attendances',
            'edit attendances',
            'manage overtime',
            'approve overtime',
            'view payroll',
            'generate payroll',
            'export payroll',
            'print payslip',
            'view leave',
            'manage leave',
            'approve leave',
            'manage leave types',
            'manage leave settings',
            'approve requests',
            'reject requests',
        ]);

        $admManager->syncPermissions([
            'view supervisor dashboard',
            'manage supervisor data',
            'export supervisor data',
        ]);

        $hrbranch->syncPermissions([
            'view departments',
            'view positions',
            'view salary_grades',
            'view employees',
            'view attendances',
            'import attendances',
            'edit attendances',
            'manage overtime',
            'approve overtime',
            'view payroll',
            'view leave',
            'manage leave',
            'approve leave',
            'view supervisor dashboard',
            'export supervisor data',
        ]);

        $hrAst->syncPermissions([
            'view departments',
            'view positions',
            'view salary_grades',
            'view employees',
            'create employees',
            'edit employees',
            'view attendances',
            'view leave',
        ]);

        $this->command->info('RolePermissionSeeder completed! 5 roles + ' . count($permissions) . ' permissions created.');
    }
}
