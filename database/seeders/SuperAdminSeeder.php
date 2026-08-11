<?php

namespace Database\Seeders;

use App\Models\Company;
use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;

class SuperAdminSeeder extends Seeder
{
    public function run(): void
    {
        // Crear super admin global
        $superAdmin = User::updateOrCreate(
            ['email' => 'superadmin@sistema.com'],
            [
                'name' => 'Super Administrador',
                'password' => 'Admin@1234',
                'phone' => '+503 0000-0000',
                'is_super_admin' => true,
                'active' => true,
            ]
        );

        // Crear admin de empresa demo
        $adminUser = User::updateOrCreate(
            ['email' => 'admin@empresademo.com'],
            [
                'name' => 'Admin Empresa',
                'password' => 'Admin@1234',
                'phone' => '+503 0001-0001',
                'is_super_admin' => false,
                'active' => true,
            ]
        );

        // Asignar admin a empresa demo
        $company = Company::where('ruc', '12345678901')->first();
        if ($company) {
            $adminRole = Role::where('slug', 'admin')->first();
            if ($adminRole) {
                $adminUser->companies()->syncWithoutDetaching([$company->id => ['role_id' => $adminRole->id]]);
            }
        }

        // Asignar todos los permisos al rol super_admin
        $superAdminRole = Role::where('slug', 'super_admin')->first();
        if ($superAdminRole) {
            $permissions = \App\Models\Permission::all();
            $superAdminRole->permissions()->sync($permissions->pluck('id'));
        }

        // Asignar permisos al rol admin
        $adminRole = Role::where('slug', 'admin')->first();
        if ($adminRole) {
            $adminPermissions = \App\Models\Permission::whereIn('slug', [
                'companies.view',
                'users.view', 'users.create', 'users.edit',
                'loans.view', 'loans.create', 'loans.edit', 'loans.approve',
                'payments.view', 'payments.create',
                'reports.view',
            ])->get();
            $adminRole->permissions()->sync($adminPermissions->pluck('id'));
        }

        // Asignar permisos al rol manager
        $managerRole = Role::where('slug', 'manager')->first();
        if ($managerRole) {
            $managerPermissions = \App\Models\Permission::whereIn('slug', [
                'loans.view', 'loans.create', 'loans.approve',
                'reports.view',
            ])->get();
            $managerRole->permissions()->sync($managerPermissions->pluck('id'));
        }

        // Asignar permisos al rol cashier
        $cashierRole = Role::where('slug', 'cashier')->first();
        if ($cashierRole) {
            $cashierPermissions = \App\Models\Permission::whereIn('slug', [
                'loans.view',
                'payments.view', 'payments.create',
            ])->get();
            $cashierRole->permissions()->sync($cashierPermissions->pluck('id'));
        }

        // Asignar permisos al rol employee
        $employeeRole = Role::where('slug', 'employee')->first();
        if ($employeeRole) {
            $employeePermissions = \App\Models\Permission::whereIn('slug', [
                'loans.view',
            ])->get();
            $employeeRole->permissions()->sync($employeePermissions->pluck('id'));
        }
    }
}
