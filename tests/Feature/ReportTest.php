<?php

namespace Tests\Feature;

use App\Models\DailyRate;
use App\Models\Item;
use App\Models\MetalType;
use App\Models\Party;
use App\Models\PartyType;
use App\Models\Purity;
use App\Models\Transaction;
use App\Models\TransactionType;
use App\Models\User;
use App\Services\GoldRateService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReportTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private Party $customer;
    private MetalType $gold;
    private Purity $p22;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $type = PartyType::create(['name' => 'Customer']);
        $this->customer = Party::create(['name' => 'Ayesha', 'phone' => '03001234567', 'party_type_id' => $type->id]);
        $this->gold = MetalType::create(['name' => 'Gold']);
        $this->p22 = Purity::create(['metal_type_id' => $this->gold->id, 'name' => '22K', 'fineness_percent' => 91.6, 'is_active' => true]);
        TransactionType::create(['name' => 'sale']);
    }

    private function sellItem(float $purchasePrice, float $saleTotal, string $date): Transaction
    {
        $item = Item::create([
            'item_code' => 'GLD-' . uniqid(),
            'item_type' => 'ring',
            'metal_type_id' => $this->gold->id,
            'purity_id' => $this->p22->id,
            'gross_weight_grams' => 10,
            'stone_weight_grams' => 0,
            'cutting_loss_grams' => 0,
            'labour_cost' => 0,
            'polish_cost' => 0,
            'purchase_rate_per_gram' => $purchasePrice / 10,
            'purchase_price' => $purchasePrice,
            'status' => 'sold',
            'created_by_user_id' => $this->user->id,
            'date_received' => $date,
        ]);

        return Transaction::create([
            'party_id' => $this->customer->id,
            'transaction_type_id' => TransactionType::where('name', 'sale')->value('id'),
            'direction' => 'OUT',
            'item_id' => $item->id,
            'metal_type_id' => $this->gold->id,
            'purity_id' => $this->p22->id,
            'weight_grams' => 10,
            'rate_per_gram' => $saleTotal / 10,
            'metal_cost' => $saleTotal,
            'labour_cost' => 0,
            'polish_cost' => 0,
            'tax_amount' => 0,
            'discount_amount' => 0,
            'deduction_percent_applied' => 0,
            'total_amount' => $saleTotal,
            'payment_method' => 'cash',
            'transaction_date' => $date,
            'created_by_user_id' => $this->user->id,
        ]);
    }

    public function test_guest_is_redirected(): void
    {
        $this->get('/reports')->assertRedirect('/login');
    }

    public function test_screen_renders_with_default_month_range(): void
    {
        $this->actingAs($this->user)->get('/reports')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('reports/index')
                ->has('filters')
                ->has('sales')
                ->has('profit')
                ->has('stock')
                ->has('rateHistory'));
    }

    public function test_sales_summary_only_counts_sale_transactions_in_range(): void
    {
        $this->sellItem(180000, 200000, '2026-06-05');
        $this->sellItem(90000, 100000, '2026-06-10');
        $this->sellItem(50000, 60000, '2026-07-01'); // outside the filtered range

        $response = $this->actingAs($this->user)->get('/reports?date_from=2026-06-01&date_to=2026-06-30')
            ->assertOk();

        $response->assertInertia(fn ($page) => $page
            ->where('sales.count', 2)
            ->where('sales.revenue', 300000)
            ->where('sales.by_metal.0.metal', 'Gold')
            ->where('sales.by_metal.0.revenue', 300000));
    }

    public function test_profit_is_sale_price_minus_the_items_own_purchase_cost(): void
    {
        $this->sellItem(purchasePrice: 180000, saleTotal: 210000, date: '2026-06-05');
        $this->sellItem(purchasePrice: 90000, saleTotal: 100000, date: '2026-06-10');

        $response = $this->actingAs($this->user)->get('/reports?date_from=2026-06-01&date_to=2026-06-30')
            ->assertOk();

        $response->assertInertia(fn ($page) => $page
            ->where('profit.revenue', 310000)
            ->where('profit.cost', 270000)
            ->where('profit.profit', 40000)
            ->where('profit.by_metal.0.metal', 'Gold')
            ->where('profit.by_metal.0.profit', 40000));
    }

    public function test_stock_valuation_uses_the_current_rate_not_the_purchase_rate(): void
    {
        // Purchased at 18,000/g; live rate is now 20,000/g — valuation must use the live rate.
        app(GoldRateService::class)->storeRate($this->gold->id, $this->p22->id, 20000, 'manual', $this->user->id);

        Item::create([
            'item_code' => 'GLD-STOCK-1',
            'item_type' => 'ring',
            'metal_type_id' => $this->gold->id,
            'purity_id' => $this->p22->id,
            'gross_weight_grams' => 10,
            'stone_weight_grams' => 0,
            'cutting_loss_grams' => 0,
            'labour_cost' => 500,
            'polish_cost' => 100,
            'purchase_rate_per_gram' => 18000,
            'purchase_price' => 180600,
            'status' => 'in_stock',
            'created_by_user_id' => $this->user->id,
            'date_received' => '2026-06-01',
        ]);

        $response = $this->actingAs($this->user)->get('/reports')->assertOk();

        // 10g × 20,000/g + 500 labour + 100 polish = 200,600 — not the 180,600 purchase price.
        $response->assertInertia(fn ($page) => $page
            ->where('stock.item_count', 1)
            ->where('stock.weight_grams', 10)
            ->where('stock.value', 200600)
            ->where('stock.by_metal.0.metal', 'Gold')
            ->where('stock.by_metal.0.value', 200600));
    }

    public function test_stock_valuation_ignores_items_not_in_stock(): void
    {
        app(GoldRateService::class)->storeRate($this->gold->id, $this->p22->id, 20000, 'manual', $this->user->id);
        $this->sellItem(180000, 200000, '2026-06-05'); // status becomes 'sold'

        $response = $this->actingAs($this->user)->get('/reports')->assertOk();

        $response->assertInertia(fn ($page) => $page
            ->where('stock.item_count', 0)
            ->where('stock.value', 0));
    }

    public function test_rate_history_uses_the_purest_active_purity_as_the_benchmark(): void
    {
        $g24 = Purity::create(['metal_type_id' => $this->gold->id, 'name' => '24K', 'fineness_percent' => 99.9, 'is_active' => true]);

        // Lower-fineness purity — must be ignored, 24K is the purer benchmark.
        DailyRate::create([
            'metal_type_id' => $this->gold->id,
            'purity_id' => $this->p22->id,
            'rate_per_gram' => 20000,
            'rate_date' => '2026-06-05',
            'source' => 'manual',
            'fetched_at' => '2026-06-05 09:00:00',
        ]);

        DailyRate::create([
            'metal_type_id' => $this->gold->id,
            'purity_id' => $g24->id,
            'rate_per_gram' => 24000,
            'rate_date' => '2026-06-05',
            'source' => 'manual',
            'fetched_at' => '2026-06-05 09:00:00',
        ]);

        $response = $this->actingAs($this->user)->get('/reports?date_from=2026-06-01&date_to=2026-06-30')->assertOk();

        $response->assertInertia(fn ($page) => $page
            ->where('rateHistory.0.metal', 'Gold')
            ->where('rateHistory.0.purity', '24K')
            ->where('rateHistory.0.points.0.rate', 24000));
    }

    public function test_rate_history_collapses_multiple_fetches_per_day_to_the_last_one(): void
    {
        $g24 = Purity::create(['metal_type_id' => $this->gold->id, 'name' => '24K', 'fineness_percent' => 99.9, 'is_active' => true]);

        DailyRate::create([
            'metal_type_id' => $this->gold->id,
            'purity_id' => $g24->id,
            'rate_per_gram' => 24000,
            'rate_date' => '2026-06-05',
            'source' => 'manual',
            'fetched_at' => '2026-06-05 09:00:00',
        ]);

        DailyRate::create([
            'metal_type_id' => $this->gold->id,
            'purity_id' => $g24->id,
            'rate_per_gram' => 24500,
            'rate_date' => '2026-06-05',
            'source' => 'manual',
            'fetched_at' => '2026-06-05 15:00:00',
        ]);

        $response = $this->actingAs($this->user)->get('/reports?date_from=2026-06-01&date_to=2026-06-30')->assertOk();

        $response->assertInertia(fn ($page) => $page
            ->where('rateHistory.0.points', [['date' => '2026-06-05', 'rate' => 24500]]));
    }

    public function test_rate_history_respects_the_date_range(): void
    {
        $g24 = Purity::create(['metal_type_id' => $this->gold->id, 'name' => '24K', 'fineness_percent' => 99.9, 'is_active' => true]);

        DailyRate::create([
            'metal_type_id' => $this->gold->id,
            'purity_id' => $g24->id,
            'rate_per_gram' => 24000,
            'rate_date' => '2026-05-01', // outside the range below
            'source' => 'manual',
            'fetched_at' => '2026-05-01 09:00:00',
        ]);

        $response = $this->actingAs($this->user)->get('/reports?date_from=2026-06-01&date_to=2026-06-30')->assertOk();

        $response->assertInertia(fn ($page) => $page->where('rateHistory.0.points', []));
    }
}
