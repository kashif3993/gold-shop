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

        // Silver's own counter margin: a flat Rs/g amount works for gold, but
        // silver's per-gram value is tiny, so a metal-level override here
        // (beats the shop default via the resolution cascade) stops silver
        // sales from quietly billing at zero markup by default. Still fully
        // editable from Rate Management.
        $silverId = DB::table('metal_types')->where('name', 'Silver')->value('id');

        if ($silverId) {
            DB::table('rate_adjustment_settings')->insert([
                'metal_type_id' => $silverId,
                'purity_id' => null,
                'adjustment_type' => 'amount',
                'adjustment_value' => 5.00,
                'is_shop_default' => 0,
            ]);
        }
    }
}
