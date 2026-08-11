<?php

namespace Database\Seeders;

use App\Models\Permission;
use Illuminate\Database\Seeder;

class PermissionSeeder extends Seeder
{
    public function run(): void
    {
        $permissions = [
            // Empresas
            ['name' => 'Ver Empresas', 'slug' => 'companies.view', 'module' => 'companies'],
            ['name' => 'Crear Empresas', 'slug' => 'companies.create', 'module' => 'companies'],
            ['name' => 'Editar Empresas', 'slug' => 'companies.edit', 'module' => 'companies'],
            ['name' => 'Eliminar Empresas', 'slug' => 'companies.delete', 'module' => 'companies'],

            // Usuarios
            ['name' => 'Ver Usuarios', 'slug' => 'users.view', 'module' => 'users'],
            ['name' => 'Crear Usuarios', 'slug' => 'users.create', 'module' => 'users'],
            ['name' => 'Editar Usuarios', 'slug' => 'users.edit', 'module' => 'users'],
            ['name' => 'Eliminar Usuarios', 'slug' => 'users.delete', 'module' => 'users'],

            // Roles
            ['name' => 'Ver Roles', 'slug' => 'roles.view', 'module' => 'roles'],
            ['name' => 'Crear Roles', 'slug' => 'roles.create', 'module' => 'roles'],
            ['name' => 'Editar Roles', 'slug' => 'roles.edit', 'module' => 'roles'],
            ['name' => 'Eliminar Roles', 'slug' => 'roles.delete', 'module' => 'roles'],

            // Cargos
            ['name' => 'Ver Cargos', 'slug' => 'cargos.view', 'module' => 'cargos'],
            ['name' => 'Crear Cargos', 'slug' => 'cargos.create', 'module' => 'cargos'],
            ['name' => 'Editar Cargos', 'slug' => 'cargos.edit', 'module' => 'cargos'],
            ['name' => 'Eliminar Cargos', 'slug' => 'cargos.delete', 'module' => 'cargos'],

            // Personal
            ['name' => 'Ver Personal', 'slug' => 'personal.view', 'module' => 'personal'],
            ['name' => 'Crear Personal', 'slug' => 'personal.create', 'module' => 'personal'],
            ['name' => 'Editar Personal', 'slug' => 'personal.edit', 'module' => 'personal'],
            ['name' => 'Eliminar Personal', 'slug' => 'personal.delete', 'module' => 'personal'],

            // Sucursales
            ['name' => 'Ver Sucursales', 'slug' => 'branches.view', 'module' => 'branches'],
            ['name' => 'Crear Sucursales', 'slug' => 'branches.create', 'module' => 'branches'],
            ['name' => 'Editar Sucursales', 'slug' => 'branches.edit', 'module' => 'branches'],
            ['name' => 'Eliminar Sucursales', 'slug' => 'branches.delete', 'module' => 'branches'],

            // Cajas
            ['name' => 'Ver Cajas', 'slug' => 'cajas.view', 'module' => 'cajas'],
            ['name' => 'Crear Cajas', 'slug' => 'cajas.create', 'module' => 'cajas'],
            ['name' => 'Editar Cajas', 'slug' => 'cajas.edit', 'module' => 'cajas'],
            ['name' => 'Eliminar Cajas', 'slug' => 'cajas.delete', 'module' => 'cajas'],
        ];

        foreach ($permissions as $permission) {
            Permission::updateOrCreate(
                ['slug' => $permission['slug']],
                $permission
            );
        }
    }
}
