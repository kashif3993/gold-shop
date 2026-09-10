<?php

namespace Database\Seeders;

use App\Models\User;
// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call([
            MetalTypeSeeder::class,
            PuritySeeder::class,
            WeightUnitSeeder::class,
            PartyTypeSeeder::class,
            TransactionTypeSeeder::class,
            BuybackDeductionSettingSeeder::class,
            RateAdjustmentSettingSeeder::class,
        ]);

        User::factory()->admin()->create([
            'username' => 'admin',
        ]);
    }
}
