<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class PartyTypeSeeder extends Seeder
{
    public function run(): void
    {
        DB::table('party_types')->insert([
            ['name' => 'Customer'],
            ['name' => 'Karigar'],
            ['name' => 'Wholesaler'],
            ['name' => 'Other Shop'],
            ['name' => 'Company'],
        ]);
    }
}
