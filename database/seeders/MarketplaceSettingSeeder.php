<?php

namespace Database\Seeders;

use App\Models\MarketplaceSetting;
use Illuminate\Database\Seeder;

class MarketplaceSettingSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        foreach ([
            'auction.default_duration_days' => ['group' => 'auction', 'value' => 7],
            'auction.anti_sniping_extension_minutes' => ['group' => 'auction', 'value' => 5],
            'auction.winner_payment_deadline_hours' => ['group' => 'auction', 'value' => 48],
            'checkout.cod_maximum_amount' => ['group' => 'checkout', 'value' => 50000],
            'settlement.hold_days' => ['group' => 'settlement', 'value' => 7],
            'settlement.minimum_payout_amount' => ['group' => 'settlement', 'value' => 5000],
        ] as $key => $setting) {
            MarketplaceSetting::query()->updateOrCreate(['key' => $key], $setting);
        }
    }
}
