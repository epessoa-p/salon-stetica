<?php

namespace Database\Seeders;

use App\Models\Company;
use Illuminate\Database\Seeder;

class CompanySeeder extends Seeder
{
    public function run(): void
    {
        Company::updateOrCreate(
            ['ruc' => '12345678901'],
            [
                'name' => 'Empresa Demo',
                'address' => 'Calle Principal 123',
                'phone' => '+503 1234-5678',
                'email' => 'info@empresademo.com',
                'description' => 'Empresa de demostración del sistema',
                'active' => true,
            ]
        );
    }
}
