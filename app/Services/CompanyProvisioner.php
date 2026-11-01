<?php

namespace App\Services;

use App\Models\Branch;
use App\Models\Caja;
use App\Models\Cargo;
use App\Models\Company;
use App\Models\PaymentMethod;
use App\Models\Personal;
use App\Models\Role;
use App\Models\StaffSchedule;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * Deja una empresa nueva lista para usarse: sucursal principal con su
 * almacén, cargo de administrador, el usuario y la ficha de personal del
 * administrador (en esa sucursal), una caja y los métodos de pago base.
 * Corre dentro de la transacción de quien llama.
 */
class CompanyProvisioner
{
    /**
     * @param  array{branch_name?: ?string, branch_address?: ?string, admin_name: string, admin_email: string, admin_password?: ?string}  $data
     * @return array{company: string, branch: string, caja: string, name: string, username: string, email: string, password: string, generated: bool}
     */
    public function provision(Company $company, array $data): array
    {
        $branchName = mb_strtoupper(trim($data['branch_name'] ?? '') ?: 'SUCURSAL PRINCIPAL');
        $address = $data['branch_address'] ?? $company->address;

        $warehouse = Warehouse::createForBranch($company->id, $branchName, $address);
        $branch = Branch::create([
            'company_id'   => $company->id,
            'warehouse_id' => $warehouse->id,
            'name'         => $branchName,
            'phone'        => $company->phone,
            'email'        => $company->email,
            'address'      => $address,
            'manager_name' => mb_strtoupper($data['admin_name']),
            'active'       => true,
        ]);

        // Rol «Administrador de Empresa»: todos los permisos de la empresa.
        $role = Role::where('slug', 'admin')->firstOrFail();
        $cargo = Cargo::withoutGlobalScopes()->firstOrCreate(
            ['company_id' => $company->id, 'name' => 'ADMINISTRADOR'],
            ['role_id' => $role->id, 'description' => 'Administra la empresa', 'active' => true],
        );

        $generated = empty($data['admin_password']);
        $password = $generated ? $this->password() : $data['admin_password'];

        $user = User::create([
            'name'           => User::uniqueUsername($data['admin_name']),
            'email'          => mb_strtolower(trim($data['admin_email'])),
            'password'       => Hash::make($password),
            'phone'          => $company->phone,
            'active'         => true,
            'is_super_admin' => false,
        ]);
        $user->companies()->syncWithoutDetaching([$company->id => ['role_id' => $role->id, 'active' => true]]);

        $personal = Personal::withoutGlobalScopes()->create([
            'company_id' => $company->id,
            'cargo_id'   => $cargo->id,
            'branch_id'  => $branch->id,
            'user_id'    => $user->id,
            'full_name'  => mb_strtoupper(trim($data['admin_name'])),
            'email'      => $user->email,
            'phone'      => $company->phone,
            'hire_date'  => today(),
            'active'     => true,
        ]);

        // Horario base de lunes a sábado, para que la agenda y la asistencia funcionen desde el día uno.
        foreach (range(1, 6) as $weekday) {
            StaffSchedule::withoutGlobalScopes()->create([
                'company_id'  => $company->id,
                'personal_id' => $personal->id,
                'weekday'     => $weekday,
                'starts_time' => '09:00:00',
                'ends_time'   => '19:00:00',
            ]);
        }

        $caja = Caja::withoutGlobalScopes()->create([
            'company_id' => $company->id,
            'branch_id'  => $branch->id,
            'name'       => 'CAJA PRINCIPAL',
            'code'       => 'CJ-01',
            'active'     => true,
            'created_by' => auth()->id() ?? $user->id,
        ]);

        PaymentMethod::seedDefaultsFor($company->id);

        return [
            'company'   => $company->name,
            'branch'    => $branch->name,
            'caja'      => $caja->name,
            'name'      => $personal->full_name,
            'username'  => $user->name,
            'email'     => $user->email,
            'password'  => $password,
            'generated' => $generated,
        ];
    }

    /** Contraseña legible de 10 caracteres (sin 0/O ni 1/l para dictarla sin errores). */
    protected function password(): string
    {
        $chars = 'abcdefghjkmnpqrstuvwxyzABCDEFGHJKLMNPQRSTUVWXYZ23456789';
        $out = '';
        for ($i = 0; $i < 10; $i++) {
            $out .= $chars[random_int(0, strlen($chars) - 1)];
        }

        return $out;
    }
}
