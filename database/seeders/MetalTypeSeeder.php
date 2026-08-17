<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class MetalTypeSeeder extends Seeder
{
    public function run(): void
    {
        DB::table('metal_types')->insert([
            ['name' => 'Gold'],
            ['name' => 'Silver'],
        ]);
    }
}
