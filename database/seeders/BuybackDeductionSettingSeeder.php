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

        // Silver needs its own, higher deduction than the gold-tuned shop
        // default: refining loss is proportionally worse and purity is less
        // certain on lower-value scrap. A metal-level row (purity_id null)
        // beats the shop default for every silver purity via the resolution
        // cascade, and stays editable from Rate Management like any override.
        $silverId = DB::table('metal_types')->where('name', 'Silver')->value('id');

        if ($silverId) {
            DB::table('buyback_deduction_settings')->insert([
                'metal_type_id' => $silverId,
                'purity_id' => null,
                'deduction_percent' => 5.00,
                'is_shop_default' => 0,
            ]);
        }
    }
}
