<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class RateAdjustmentSettingSeeder extends Seeder
{
    public function run(): void
    {
        DB::table('rate_adjustment_settings')->insert([
            'metal_type_id' => null,
            'purity_id' => null,
            'adjustment_type' => 'amount',
            'adjustment_value' => 0.00,
            'is_shop_default' => 1,
        ]);
    }
}
