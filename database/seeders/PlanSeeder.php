<?php

namespace Database\Seeders;

use App\Models\Plan;
use Illuminate\Database\Seeder;

class PlanSeeder extends Seeder
{
    public function run(): void
    {
        $plans = [
            [
                'name' => 'Básico',
                'slug' => 'basico',
                'description' => 'Ideal para un salón pequeño de una sola sucursal.',
                'price' => 99.00,
                'currency' => 'BOB',
                'billing_days' => 30,
                'trial_days' => 14,
                'max_branches' => 1,
                'max_personal' => 5,
                'max_cajas' => 1,
                'max_users' => 3,
                'features' => ['crm', 'scheduling', 'catalog', 'sales', 'finance'],
                'active' => true,
                'sort_order' => 1,
            ],
            [
                'name' => 'Pro',
                'slug' => 'pro',
                'description' => 'Para salones en crecimiento con varias sucursales.',
                'price' => 199.00,
                'currency' => 'BOB',
                'billing_days' => 30,
                'trial_days' => 14,
                'max_branches' => 3,
                'max_personal' => 20,
                'max_cajas' => 5,
                'max_users' => 10,
                'features' => ['crm', 'scheduling', 'catalog', 'sales', 'finance', 'inventory', 'purchasing'],
                'active' => true,
                'sort_order' => 2,
            ],
            [
                'name' => 'Premium',
                'slug' => 'premium',
                'description' => 'Uso ilimitado para cadenas de salones.',
                'price' => 349.00,
                'currency' => 'BOB',
                'billing_days' => 30,
                'trial_days' => 14,
                'max_branches' => null,
                'max_personal' => null,
                'max_cajas' => null,
                'max_users' => null,
                'features' => ['crm', 'scheduling', 'catalog', 'sales', 'finance', 'inventory', 'purchasing', 'marketing'],
                'active' => true,
                'sort_order' => 3,
            ],
        ];

        foreach ($plans as $plan) {
            Plan::updateOrCreate(['slug' => $plan['slug']], $plan);
        }
    }
}
