<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class PuritySeeder extends Seeder
{
    public function run(): void
    {
        $goldId = DB::table('metal_types')->where('name', 'Gold')->value('id');
        $silverId = DB::table('metal_types')->where('name', 'Silver')->value('id');

        DB::table('purities')->insert([
            ['metal_type_id' => $goldId, 'name' => '24K', 'fineness_percent' => 99.90, 'is_active' => 1],
            ['metal_type_id' => $goldId, 'name' => '22K', 'fineness_percent' => 91.60, 'is_active' => 1],
            ['metal_type_id' => $goldId, 'name' => '21K', 'fineness_percent' => 87.50, 'is_active' => 1],
            ['metal_type_id' => $goldId, 'name' => '18K', 'fineness_percent' => 75.00, 'is_active' => 1],
            ['metal_type_id' => $silverId, 'name' => 'Fine Silver (999)', 'fineness_percent' => 99.90, 'is_active' => 1],
            ['metal_type_id' => $silverId, 'name' => 'Sterling Silver (925)', 'fineness_percent' => 92.50, 'is_active' => 1],
            ['metal_type_id' => $silverId, 'name' => 'Coin Silver (900)', 'fineness_percent' => 90.00, 'is_active' => 1],
            ['metal_type_id' => $silverId, 'name' => '835 Silver', 'fineness_percent' => 83.50, 'is_active' => 1],
        ]);
    }
}
