<?php

namespace Database\Seeders;

use App\Models\Role;
use Illuminate\Database\Seeder;

class RoleSeeder extends Seeder
{
    public function run(): void
    {
        $roles = [
            [
                'name' => 'Super Administrador',
                'slug' => 'super_admin',
                'description' => 'Acceso completo al sistema',
            ],
            [
                'name' => 'Administrador de Empresa',
                'slug' => 'admin',
                'description' => 'Administrador de una empresa específica',
            ],
            [
                'name' => 'Gerente',
                'slug' => 'manager',
                'description' => 'Gerente de préstamos',
            ],
            [
                'name' => 'Cajero',
                'slug' => 'cashier',
                'description' => 'Operador de caja y pagos',
            ],
            [
                'name' => 'Empleado',
                'slug' => 'employee',
                'description' => 'Empleado básico',
            ],
        ];

        foreach ($roles as $role) {
            Role::updateOrCreate(
                ['slug' => $role['slug']],
                $role
            );
        }
    }
}
