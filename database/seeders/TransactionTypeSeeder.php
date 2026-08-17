<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class TransactionTypeSeeder extends Seeder
{
    public function run(): void
    {
        DB::table('transaction_types')->insert([
            ['name' => 'sale'],
            ['name' => 'purchase'],
            ['name' => 'old_gold_exchange'],
            ['name' => 'buy_back'],
            ['name' => 'karigar_issue'],
            ['name' => 'karigar_return'],
            ['name' => 'shop_transfer'],
            ['name' => 'wholesale_purchase'],
        ]);
    }
}
