<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class BuybackDeductionSettingSeeder extends Seeder
{
    public function run(): void
    {
        DB::table('buyback_deduction_settings')->insert([
            'metal_type_id' => null,
            'purity_id' => null,
            'deduction_percent' => 2.50,
            'is_shop_default' => 1,
        ]);
    }
}
