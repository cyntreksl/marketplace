<?php

namespace Database\Seeders;

use App\Models\Role;
use Illuminate\Database\Seeder;

class RoleSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        foreach ([
            Role::Buyer => 'Buyer',
            Role::IndividualSeller => 'Individual Seller',
            Role::BusinessSeller => 'Business Seller',
            Role::Admin => 'Admin / Operations',
            Role::FinanceAdmin => 'Finance Admin',
            Role::SuperAdmin => 'Super Admin',
        ] as $name => $label) {
            Role::query()->updateOrCreate(['name' => $name], ['label' => $label]);
        }
    }
}
