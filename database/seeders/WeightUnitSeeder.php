<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class WeightUnitSeeder extends Seeder
{
    public function run(): void
    {
        DB::table('weight_units')->insert([
            ['name' => 'gram', 'grams_per_unit' => 1.000000],
            ['name' => 'tola', 'grams_per_unit' => 11.663800],
            ['name' => 'masha', 'grams_per_unit' => 0.971983],
            ['name' => 'ratti', 'grams_per_unit' => 0.121498],
            ['name' => 'point', 'grams_per_unit' => 0.010000],
        ]);
    }
}
