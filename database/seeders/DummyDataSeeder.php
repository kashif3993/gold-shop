<?php

namespace Database\Seeders;

use App\Models\AuditLog;
use App\Models\DailyRate;
use App\Models\Invoice;
use App\Models\InvoiceLineItem;
use App\Models\Item;
use App\Models\MetalType;
use App\Models\Party;
use App\Models\PartyType;
use App\Models\Purity;
use App\Models\Transaction;
use App\Models\TransactionType;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * End-to-end demo data for manual testing of everything built so far:
 * reference data, users, daily rates (+ history + a stale row), parties of
 * every type, a varied inventory, completed POS sales (flat + % discount,
 * every payment method, walk-in + named), an old-gold exchange sale, buy-back
 * transactions linked to real earlier sales, karigar / wholesale / shop-transfer
 * dealings so party ledgers carry non-trivial balances, and a couple of audit rows.
 *
 * Safe to re-run — it wipes its own transactional data first and rebuilds from a
 * fixed script, so you always land on the same known state:
 *
 *   php artisan db:seed --class=DummyDataSeeder
 *
 * Reference tables (metals/purities/party types/etc.) are seeded automatically
 * if empty, so this also works on a completely fresh `migrate:fresh`.
 */
class DummyDataSeeder extends Seeder
{
    /** @var array<string,int> metal name => id */
    private array $metals = [];

    /** @var array<string,int> purity name => id */
    private array $purities = [];

    /** @var array<int,float> purity id => current rate per gram */
    private array $rates = [];

    /** @var array<string,int> party name => id */
    private array $parties = [];

    /** @var array<string,int> transaction type name => id */
    private array $txnTypes = [];

    /** @var array<string,int> running invoice sequence per YYYYMMDD */
    private array $invoiceSeq = [];

    private User $admin;

    private User $shopkeeper;

    public function run(): void
    {
        $this->ensureReferenceData();
        $this->wipeDemoData();

        $this->loadLookups();
        $this->seedUsers();
        $this->seedDailyRates();
        $this->seedParties();
        $this->seedInventory();
        $this->seedSales();
        $this->seedExchangeSale();
        $this->seedBuyBacks();
        $this->seedLedgerDealings();
        $this->seedAuditLog();

        $this->command->info('Demo data ready. Login: admin / password  (also: shopkeeper / password)');
    }

    /* ───────────────────────── setup ───────────────────────── */

    private function ensureReferenceData(): void
    {
        if (DB::table('metal_types')->count() === 0) {
            $this->call([
                MetalTypeSeeder::class,
                PuritySeeder::class,
                WeightUnitSeeder::class,
                PartyTypeSeeder::class,
                TransactionTypeSeeder::class,
                BuybackDeductionSettingSeeder::class,
                RateAdjustmentSettingSeeder::class,
            ]);
        }
    }

    /**
     * Remove everything this seeder creates, child tables first, so the run is
     * idempotent. Reference data and the real `admin` user are left alone.
     */
    private function wipeDemoData(): void
    {
        Schema::disableForeignKeyConstraints();
        foreach (['audit_log', 'invoice_line_items', 'transactions', 'invoices', 'items', 'daily_rates', 'parties'] as $table) {
            DB::table($table)->truncate();
        }
        DB::table('users')->where('username', '!=', 'admin')->delete();
        Schema::enableForeignKeyConstraints();
    }

    private function loadLookups(): void
    {
        $this->metals = MetalType::pluck('id', 'name')->all();
        $this->purities = Purity::pluck('id', 'name')->all();
        $this->txnTypes = TransactionType::pluck('id', 'name')->all();
    }

    private function seedUsers(): void
    {
        $this->admin = User::firstOrCreate(
            ['username' => 'admin'],
            [
                'full_name' => 'Admin User',
                'email' => 'admin@zarnoor.test',
                'password_hash' => 'password',
                'role' => 'admin',
                'is_active' => true,
            ],
        );

        $this->shopkeeper = User::create([
            'full_name' => 'Bilal Shopkeeper',
            'username' => 'shopkeeper',
            'email' => 'bilal@zarnoor.test',
            'password_hash' => 'password',
            'role' => 'shopkeeper',
            'is_active' => true,
        ]);

        User::create([
            'full_name' => 'Zoya Counter Staff',
            'username' => 'zoya',
            'email' => null,
            'password_hash' => 'password',
            'role' => 'shopkeeper',
            'is_active' => false, // inactive user, for testing the is_active gate
        ]);
    }

    /* ───────────────────────── daily rates ───────────────────────── */

    private function seedDailyRates(): void
    {
        // Rough PKR-per-gram figures; only relative sanity matters for demo.
        $current = [
            '24K' => 24500.00,
            '22K' => 22460.00,
            '21K' => 21440.00,
            '18K' => 18375.00,
            'Fine Silver (999)' => 312.00,
            'Sterling Silver (925)' => 289.00,
            'Coin Silver (900)' => 281.00,
            '835 Silver' => 261.00,
        ];

        foreach ($current as $purityName => $rate) {
            $purityId = $this->purities[$purityName] ?? null;
            if (! $purityId) {
                continue;
            }
            $metalId = (int) Purity::find($purityId)->metal_type_id;
            $this->rates[$purityId] = $rate;

            // 3 days of history (older = not current), then today's current rate.
            $history = [
                ['days' => 6, 'factor' => 0.972, 'current' => false, 'stale' => true,  'source' => 'api'],
                ['days' => 3, 'factor' => 0.987, 'current' => false, 'stale' => false, 'source' => 'api'],
                ['days' => 1, 'factor' => 0.995, 'current' => false, 'stale' => false, 'source' => 'manual'],
                ['days' => 0, 'factor' => 1.000, 'current' => true,  'stale' => false, 'source' => 'api'],
            ];

            foreach ($history as $h) {
                $raw = round($rate * $h['factor'], 2);
                DailyRate::create([
                    'metal_type_id' => $metalId,
                    'purity_id' => $purityId,
                    'api_raw_rate_per_gram' => $h['source'] === 'api' ? round($raw - 15, 2) : null,
                    'adjustment_type_used' => $h['source'] === 'api' ? 'amount' : null,
                    'adjustment_value_used' => $h['source'] === 'api' ? 15.0000 : null,
                    'rate_per_gram' => $raw,
                    'rate_date' => Carbon::today()->subDays($h['days'])->toDateString(),
                    'source' => $h['source'],
                    'fetched_at' => Carbon::today()->subDays($h['days'])->setTime(10, 5),
                    'is_current' => $h['current'],
                    'is_stale' => $h['stale'],
                    'entered_by_user_id' => $h['source'] === 'manual' ? $this->admin->id : null,
                ]);
            }
        }
    }

    /* ───────────────────────── parties ───────────────────────── */

    private function seedParties(): void
    {
        $types = PartyType::pluck('id', 'name');

        $rows = [
            ['Customer', 'Ahmed Khan', '0300 1112233', 'Gulberg III, Lahore', true],
            ['Customer', 'Sara Ali', '0321 4455667', 'DHA Phase 5, Karachi', true],
            ['Customer', 'Fatima Noor', '0333 7788990', 'Bahria Town, Rawalpindi', true],
            ['Customer', 'Imran Malik', '0345 2223344', 'Model Town, Lahore', true],
            ['Customer', 'Walk-in Customer', '0000000000', null, true],
            ['Karigar', 'Ustad Bashir', '0333 9988776', 'Sonar Bazaar, Rawalpindi', true],
            ['Karigar', 'Rehmat Goldsmith', '0345 1230000', 'Anarkali, Lahore', true],
            ['Wholesaler', 'Al-Madina Gold Traders', '042 35555555', 'Shah Alam Market, Lahore', true],
            ['Wholesaler', 'Sarafa Bazaar Traders', '051 2666666', 'Sarafa Bazaar, Rawalpindi', true],
            ['Other Shop', 'Noor Jewellers', '051 2777777', 'Saddar, Rawalpindi', true],
            ['Other Shop', 'Shalimar Gold', '042 37000000', 'Liberty Market, Lahore', false],
            ['Company', 'Pak Gold Refinery (Pvt) Ltd', '021 34999999', 'SITE Area, Karachi', true],
        ];

        foreach ($rows as [$type, $name, $phone, $address, $active]) {
            if (! isset($types[$type])) {
                continue;
            }
            $party = Party::create([
                'party_type_id' => $types[$type],
                'name' => $name,
                'phone' => $phone,
                'address' => $address,
                'notes' => null,
                'is_active' => $active,
            ]);
            $this->parties[$name] = $party->id;
        }
    }

    /* ───────────────────────── inventory ───────────────────────── */

    private function seedInventory(): void
    {
        // [type, metal, purity, gross, stone, cutting, labour, polish, rate, sourceParty, daysAgo]
        $specs = [
            ['ring',     'Gold',   '22K', 8.500,  0.400, 0.120, 6500,  900,  22000, 'Ustad Bashir',            38],
            ['ring',     'Gold',   '21K', 6.200,  0.000, 0.080, 5200,  700,  21000, 'Rehmat Goldsmith',        37],
            ['ring',     'Gold',   '18K', 4.150,  0.900, 0.050, 4800,  600,  18000, 'Rehmat Goldsmith',        35],
            ['bangle',   'Gold',   '22K', 24.800, 0.000, 0.350, 14500, 1800, 22050, 'Ustad Bashir',            34],
            ['bangle',   'Gold',   '22K', 22.350, 0.000, 0.300, 13800, 1700, 22050, 'Ustad Bashir',            33],
            ['necklace', 'Gold',   '22K', 41.600, 2.100, 0.500, 26000, 3200, 22100, 'Al-Madina Gold Traders',  30],
            ['necklace', 'Gold',   '21K', 33.200, 1.400, 0.420, 21000, 2600, 21050, 'Al-Madina Gold Traders',  29],
            ['earring',  'Gold',   '22K', 7.700,  0.600, 0.090, 5600,  650,  22000, 'Ustad Bashir',            27],
            ['earring',  'Gold',   '18K', 5.300,  0.750, 0.060, 4300,  520,  18100, 'Rehmat Goldsmith',        26],
            ['chain',    'Gold',   '21K', 12.900, 0.000, 0.160, 8200,  980,  21000, 'Rehmat Goldsmith',        24],
            ['pendant',  'Gold',   '22K', 3.400,  0.350, 0.040, 2900,  360,  22000, 'Ustad Bashir',            22],
            ['tops',     'Gold',   '22K', 2.100,  0.180, 0.030, 1900,  240,  22000, 'Ustad Bashir',            21],
            ['bracelet', 'Gold',   '18K', 9.600,  0.000, 0.110, 6100,  760,  18050, 'Rehmat Goldsmith',        19],
            ['biscuit',  'Gold',   '24K', 10.000, 0.000, 0.000, 0,     0,    24350, 'Pak Gold Refinery (Pvt) Ltd', 18],
            ['biscuit',  'Gold',   '24K', 20.000, 0.000, 0.000, 0,     0,    24350, 'Pak Gold Refinery (Pvt) Ltd', 18],
            ['coin',     'Gold',   '24K', 5.000,  0.000, 0.000, 350,   0,    24400, 'Pak Gold Refinery (Pvt) Ltd', 15],
            ['piece',    'Gold',   '24K', 33.500, 0.000, 0.000, 0,     0,    24300, 'Sarafa Bazaar Traders',   14],
            ['necklace', 'Silver', 'Fine Silver (999)',      120.000, 4.000, 1.200, 3500, 400, 305, 'Sarafa Bazaar Traders', 12],
            ['ring',     'Silver', 'Sterling Silver (925)',  9.400,   0.600, 0.100, 900,  120, 285, 'Sarafa Bazaar Traders', 10],
            ['bracelet', 'Silver', 'Sterling Silver (925)',  38.700,  0.000, 0.320, 2100, 260, 286, 'Sarafa Bazaar Traders', 9],
        ];

        $seq = ['GLD-RNG' => 0, 'GLD-BNG' => 0, 'GLD-NCK' => 0, 'GLD-ERN' => 0, 'GLD-CHN' => 0,
            'GLD-PND' => 0, 'GLD-TPS' => 0, 'GLD-BRC' => 0, 'GLD-BSC' => 0, 'GLD-CON' => 0,
            'GLD-PCE' => 0, 'SLV-NCK' => 0, 'SLV-RNG' => 0, 'SLV-BRC' => 0];

        $typeAbbr = ['ring' => 'RNG', 'bangle' => 'BNG', 'necklace' => 'NCK', 'earring' => 'ERN',
            'chain' => 'CHN', 'pendant' => 'PND', 'tops' => 'TPS', 'bracelet' => 'BRC',
            'biscuit' => 'BSC', 'coin' => 'CON', 'piece' => 'PCE'];

        foreach ($specs as [$type, $metal, $purityName, $gross, $stone, $cutting, $labour, $polish, $rate, $sourceName, $daysAgo]) {
            $prefix = ($metal === 'Gold' ? 'GLD' : 'SLV') . '-' . $typeAbbr[$type];
            $seq[$prefix] = ($seq[$prefix] ?? 0) + 1;
            $code = sprintf('%s-%04d', $prefix, $seq[$prefix]);

            $net = round($gross - $stone - $cutting, 3);
            $purchasePrice = round(($net * $rate) + $labour + $polish, 2);

            $this->makeItem([
                'item_code' => $code,
                'item_type' => $type,
                'metal_type_id' => $this->metals[$metal],
                'purity_id' => $this->purities[$purityName],
                'gross_weight_grams' => $gross,
                'stone_weight_grams' => $stone,
                'cutting_loss_grams' => $cutting,
                'labour_cost' => $labour,
                'polish_cost' => $polish,
                'purchase_rate_per_gram' => $rate,
                'purchase_price' => $purchasePrice,
                'source_party_id' => $this->parties[$sourceName] ?? null,
                'date_received' => Carbon::today()->subDays($daysAgo)->toDateString(),
                'status' => 'in_stock',
                'created_by_user_id' => $this->admin->id,
            ]);
        }
    }

    private function makeItem(array $attrs): Item
    {
        $item = new Item($attrs);
        // status / created_by aren't in $fillable-sensitive here; set directly.
        $item->status = $attrs['status'];
        $item->created_by_user_id = $attrs['created_by_user_id'];
        $item->save();

        $item->refresh(); // pick up the DB-generated net_weight_grams
        $item->qr_payload = json_encode([
            'id' => $item->id,
            'code' => $item->item_code,
            'net_wt' => (float) $item->net_weight_grams,
        ]);
        $item->saveQuietly();

        return $item;
    }

    /* ───────────────────────── POS sales ───────────────────────── */

    private function seedSales(): void
    {
        // Each: customer name (null = walk-in), payment, discount value + type, days ago, item codes.
        $sales = [
            ['Ahmed Khan',   'cash',          5000, 'flat',       28, ['GLD-RNG-0001']],
            ['Sara Ali',     'card',          10,   'percentage', 25, ['GLD-BNG-0001', 'GLD-ERN-0001']],
            ['Fatima Noor',  'bank_transfer', 0,    'flat',       20, ['GLD-NCK-0001']],
            [null,           'cash',          2500, 'flat',       16, ['GLD-TPS-0001', 'GLD-PND-0001']],
            ['Imran Malik',  'credit',        7.5,  'percentage', 11, ['GLD-CHN-0001', 'GLD-BRC-0001']],
            ['Ahmed Khan',   'cash',          0,    'flat',        5, ['SLV-RNG-0001']],
        ];

        foreach ($sales as [$customer, $payment, $discount, $discountType, $daysAgo, $codes]) {
            $items = Item::whereIn('item_code', $codes)->get();
            if ($items->count() !== count($codes)) {
                continue;
            }
            $this->buildSaleInvoice(
                $items,
                Carbon::today()->subDays($daysAgo),
                $customer,
                $payment,
                (float) $discount,
                $discountType,
                $discount > 0 ? 'Regular customer goodwill' : null,
            );
        }
    }

    /**
     * Mirrors POSController::store math (all components rounded to 2dp so the
     * invoice totals equal the exact sum of the stored line items).
     *
     * @param  \Illuminate\Support\Collection<int,Item>  $items
     */
    private function buildSaleInvoice(
        $items,
        Carbon $date,
        ?string $customerName,
        string $payment,
        float $discount,
        string $discountType,
        ?string $discountReason,
        array $exchanges = [],
    ): Invoice {
        $partyId = $this->parties[$customerName ?? 'Walk-in Customer'] ?? $this->parties['Walk-in Customer'];

        $totalMetal = 0.0;
        $totalLabour = 0.0;
        $totalPolish = 0.0;
        $lines = [];

        foreach ($items as $item) {
            $rate = $this->rates[$item->purity_id] ?? (float) $item->purchase_rate_per_gram;
            $metal = round(((float) $item->net_weight_grams) * $rate, 2);
            $labour = round((float) $item->labour_cost, 2);
            $polish = round((float) $item->polish_cost, 2);
            $lineTotal = round($metal + $labour + $polish, 2);

            $totalMetal += $metal;
            $totalLabour += $labour;
            $totalPolish += $polish;

            $lines[] = compact('item', 'rate', 'metal', 'labour', 'polish', 'lineTotal');
        }

        $subtotal = round($totalMetal + $totalLabour + $totalPolish, 2);
        $actualDiscount = $discountType === 'percentage'
            ? round($subtotal * $discount / 100, 2)
            : round($discount, 2);

        $totalExchange = 0.0;
        foreach ($exchanges as $exc) {
            $totalExchange += round((float) $exc['valuation'], 2);
        }
        $totalExchange = round($totalExchange, 2);

        $grandTotal = round($subtotal - $actualDiscount - $totalExchange, 2);

        $ymd = $date->format('Ymd');
        $this->invoiceSeq[$ymd] = ($this->invoiceSeq[$ymd] ?? 0) + 1;
        $invoiceNumber = sprintf('INV-%s-%04d', $ymd, $this->invoiceSeq[$ymd]);

        $invoice = Invoice::create([
            'invoice_number' => $invoiceNumber,
            'party_id' => $partyId,
            'invoice_date' => $date->toDateString(),
            'total_metal_cost' => round($totalMetal, 2),
            'total_labour_cost' => round($totalLabour, 2),
            'total_polish_cost' => round($totalPolish, 2),
            'total_tax' => 0,
            'total_discount' => $actualDiscount,
            'discount_reason' => $discountReason,
            'total_exchange_deduction' => $totalExchange,
            'grand_total' => $grandTotal,
            'payment_method' => $payment,
            'created_by_user_id' => $this->shopkeeper->id,
        ]);

        $saleType = $this->txnTypes['sale'];
        foreach ($lines as $l) {
            /** @var Item $item */
            $item = $l['item'];
            $item->update(['status' => 'sold']);

            $txn = Transaction::create([
                'party_id' => $partyId,
                'transaction_type_id' => $saleType,
                'direction' => 'OUT',
                'item_id' => $item->id,
                'metal_type_id' => $item->metal_type_id,
                'purity_id' => $item->purity_id,
                'weight_grams' => $item->net_weight_grams,
                'rate_per_gram' => $l['rate'],
                'metal_cost' => $l['metal'],
                'labour_cost' => $l['labour'],
                'polish_cost' => $l['polish'],
                'tax_amount' => 0,
                'discount_amount' => 0,
                'deduction_percent_applied' => 0,
                'total_amount' => $l['lineTotal'],
                'invoice_id' => $invoice->id,
                'payment_method' => $payment,
                'transaction_date' => $date->toDateString(),
                'created_by_user_id' => $this->shopkeeper->id,
            ]);

            InvoiceLineItem::create([
                'invoice_id' => $invoice->id,
                'item_id' => $item->id,
                'transaction_id' => $txn->id,
                'weight_grams' => $item->net_weight_grams,
                'purity_id' => $item->purity_id,
                'rate_per_gram' => $l['rate'],
                'metal_cost' => $l['metal'],
                'labour_cost' => $l['labour'],
                'polish_cost' => $l['polish'],
                'line_total' => $l['lineTotal'],
            ]);
        }

        foreach ($exchanges as $exc) {
            $this->recordExchangeLeg($invoice, $partyId, $exc, $date, $payment);
        }

        return $invoice;
    }

    private function recordExchangeLeg(Invoice $invoice, int $partyId, array $exc, Carbon $date, string $payment): void
    {
        $netWeight = round($exc['weight_grams'] * (1 - $exc['deduction_percent'] / 100), 3);

        $scrap = $this->makeItem([
            'item_code' => 'EXC-' . strtoupper(bin2hex(random_bytes(4))),
            'item_type' => 'old_gold',
            'metal_type_id' => $exc['metal_type_id'],
            'purity_id' => $exc['purity_id'],
            'gross_weight_grams' => $exc['weight_grams'],
            'stone_weight_grams' => 0,
            'cutting_loss_grams' => round($exc['weight_grams'] - $netWeight, 3),
            'labour_cost' => 0,
            'polish_cost' => 0,
            'purchase_rate_per_gram' => $exc['rate_per_gram'],
            'purchase_price' => $exc['valuation'],
            'source_party_id' => $partyId,
            'date_received' => $date->toDateString(),
            'status' => 'bought_back',
            'created_by_user_id' => $this->shopkeeper->id,
        ]);

        Transaction::create([
            'party_id' => $partyId,
            'transaction_type_id' => $this->txnTypes['old_gold_exchange'],
            'direction' => 'IN',
            'item_id' => $scrap->id,
            'metal_type_id' => $exc['metal_type_id'],
            'purity_id' => $exc['purity_id'],
            'weight_grams' => $exc['weight_grams'],
            'rate_per_gram' => $exc['rate_per_gram'],
            'metal_cost' => $exc['valuation'],
            'labour_cost' => 0,
            'polish_cost' => 0,
            'tax_amount' => 0,
            'discount_amount' => 0,
            'deduction_percent_applied' => $exc['deduction_percent'],
            'total_amount' => $exc['valuation'],
            'invoice_id' => $invoice->id,
            'payment_method' => $payment,
            'transaction_date' => $date->toDateString(),
            'created_by_user_id' => $this->shopkeeper->id,
        ]);
    }

    /* ───────────────────────── exchange sale ───────────────────────── */

    private function seedExchangeSale(): void
    {
        $item = Item::where('item_code', 'GLD-BNG-0002')->first();
        if (! $item) {
            return;
        }

        $rate = $this->rates[$this->purities['22K']];
        $scrapWeight = 12.400;
        $deduction = 8.0;
        $valuation = round($scrapWeight * (1 - $deduction / 100) * $rate, 2);

        $this->buildSaleInvoice(
            collect([$item]),
            Carbon::today()->subDays(8),
            'Sara Ali',
            'cash',
            0,
            'flat',
            null,
            [[
                'metal_type_id' => $this->metals['Gold'],
                'purity_id' => $this->purities['22K'],
                'weight_grams' => $scrapWeight,
                'deduction_percent' => $deduction,
                'rate_per_gram' => $rate,
                'valuation' => $valuation,
            ]],
        );
    }

    /* ───────────────────────── buy-backs ───────────────────────── */

    private function seedBuyBacks(): void
    {
        $service = app(\App\Services\BuybackDeductionService::class);

        // Buy back an item we sold earlier — value is recomputed from a fresh
        // re-weigh + today's rate, fully independent of the original sale.
        $original = Transaction::where('direction', 'OUT')
            ->whereHas('item', fn ($q) => $q->where('item_code', 'GLD-RNG-0001'))
            ->first();

        if ($original) {
            $reweigh = 7.980; // slightly lighter after wear
            $rate = $this->rates[$original->purity_id] ?? (float) $original->rate_per_gram;
            $deduction = $service->resolveDeductionPercent($original->purity_id, $original->metal_type_id);
            $valuation = $service->calculateValuation($reweigh, $rate, $deduction);

            $this->recordBuyBack(
                partyName: 'Ahmed Khan',
                metalId: $original->metal_type_id,
                purityId: $original->purity_id,
                reweigh: $reweigh,
                rate: $rate,
                deduction: $deduction,
                valuation: $valuation,
                date: Carbon::today()->subDays(2),
                originalSaleId: $original->id,
                notes: 'Customer returned gold ring, re-weighed and valued at current rate.',
            );
        }

        // Walk-in scrap buy-back with no linked sale at all.
        $rate = $this->rates[$this->purities['21K']];
        $deduction = $service->resolveDeductionPercent($this->purities['21K'], $this->metals['Gold']);
        $valuation = $service->calculateValuation(18.250, $rate, $deduction);

        $this->recordBuyBack(
            partyName: 'Walk-in Customer',
            metalId: $this->metals['Gold'],
            purityId: $this->purities['21K'],
            reweigh: 18.250,
            rate: $rate,
            deduction: $deduction,
            valuation: $valuation,
            date: Carbon::today()->subDays(1),
            originalSaleId: null,
            notes: 'Walk-in scrap gold, no prior sale.',
        );
    }

    private function recordBuyBack(
        string $partyName,
        int $metalId,
        int $purityId,
        float $reweigh,
        float $rate,
        float $deduction,
        array $valuation,
        Carbon $date,
        ?int $originalSaleId,
        string $notes,
    ): void {
        $partyId = $this->parties[$partyName];
        $total = $valuation['total_amount'];

        $item = $this->makeItem([
            'item_code' => 'BB-' . strtoupper(bin2hex(random_bytes(4))),
            'item_type' => 'old_gold',
            'metal_type_id' => $metalId,
            'purity_id' => $purityId,
            'gross_weight_grams' => $reweigh,
            'stone_weight_grams' => 0,
            'cutting_loss_grams' => round($reweigh - $valuation['effective_weight_grams'], 3),
            'labour_cost' => 0,
            'polish_cost' => 0,
            'purchase_rate_per_gram' => $rate,
            'purchase_price' => $total,
            'source_party_id' => $partyId,
            'date_received' => $date->toDateString(),
            'status' => 'bought_back',
            'created_by_user_id' => $this->shopkeeper->id,
        ]);

        Transaction::create([
            'party_id' => $partyId,
            'transaction_type_id' => $this->txnTypes['buy_back'],
            'direction' => 'IN',
            'item_id' => $item->id,
            'metal_type_id' => $metalId,
            'purity_id' => $purityId,
            'weight_grams' => $reweigh,
            'rate_per_gram' => $rate,
            'metal_cost' => $total,
            'labour_cost' => 0,
            'polish_cost' => 0,
            'tax_amount' => 0,
            'discount_amount' => 0,
            'deduction_percent_applied' => $deduction,
            'total_amount' => $total,
            'original_sale_transaction_id' => $originalSaleId,
            'payment_method' => 'cash',
            'transaction_date' => $date->toDateString(),
            'notes' => $notes,
            'created_by_user_id' => $this->shopkeeper->id,
        ]);
    }

    /* ───────────────── karigar / wholesale / shop-transfer dealings ───────────────── */

    private function seedLedgerDealings(): void
    {
        $gold = $this->metals['Gold'];
        $p22 = $this->purities['22K'];
        $p24 = $this->purities['24K'];
        $rate22 = $this->rates[$p22];
        $rate24 = $this->rates[$p24];

        // Issue pure gold to a karigar to make jewelry (they owe us metal → OUT).
        $this->plainTxn('karigar_issue', 'OUT', 'Ustad Bashir', $gold, $p24, 55.000, $rate24,
            Carbon::today()->subDays(20), 'Issued 24K for bangle order');

        // Karigar returns finished pieces (offsets the issue → IN).
        $this->plainTxn('karigar_return', 'IN', 'Ustad Bashir', $gold, $p22, 52.300, $rate22,
            Carbon::today()->subDays(12), 'Returned 2 finished bangles');

        // Bulk purchase from a wholesaler (we owe them → IN).
        $this->plainTxn('wholesale_purchase', 'IN', 'Al-Madina Gold Traders', $gold, $p22, 100.000, $rate22,
            Carbon::today()->subDays(18), 'Bulk 22K purchase, partial payment pending');

        $this->plainTxn('wholesale_purchase', 'IN', 'Sarafa Bazaar Traders', $this->metals['Silver'],
            $this->purities['Fine Silver (999)'], 2000.000, $this->rates[$this->purities['Fine Silver (999)']],
            Carbon::today()->subDays(15), 'Silver stock top-up');

        // Transfer stock to another shop (they owe us → OUT).
        $this->plainTxn('shop_transfer', 'OUT', 'Noor Jewellers', $gold, $p22, 15.750, $rate22,
            Carbon::today()->subDays(7), 'Lent 22K chain stock to Noor Jewellers');
    }

    private function plainTxn(
        string $type,
        string $direction,
        string $partyName,
        int $metalId,
        int $purityId,
        float $weight,
        float $rate,
        Carbon $date,
        string $notes,
    ): void {
        $total = round($weight * $rate, 2);

        Transaction::create([
            'party_id' => $this->parties[$partyName],
            'transaction_type_id' => $this->txnTypes[$type],
            'direction' => $direction,
            'item_id' => null,
            'metal_type_id' => $metalId,
            'purity_id' => $purityId,
            'weight_grams' => $weight,
            'rate_per_gram' => $rate,
            'metal_cost' => $total,
            'labour_cost' => 0,
            'polish_cost' => 0,
            'tax_amount' => 0,
            'discount_amount' => 0,
            'deduction_percent_applied' => null,
            'total_amount' => $total,
            'invoice_id' => null,
            'payment_method' => 'na',
            'transaction_date' => $date->toDateString(),
            'notes' => $notes,
            'created_by_user_id' => $this->admin->id,
        ]);
    }

    /* ───────────────────────── audit log ───────────────────────── */

    private function seedAuditLog(): void
    {
        $invoice = Invoice::whereNotNull('discount_reason')->first();
        if ($invoice) {
            AuditLog::create([
                'entity_type' => 'discount',
                'entity_id' => $invoice->id,
                'field_name' => 'total_discount',
                'old_value' => '0.00',
                'new_value' => (string) $invoice->total_discount,
                'reason' => 'Manager-approved loyalty discount',
                'changed_by_user_id' => $this->admin->id,
            ]);
        }

        $item = Item::where('item_code', 'GLD-NCK-0001')->first();
        if ($item) {
            AuditLog::create([
                'entity_type' => 'item_price',
                'entity_id' => $item->id,
                'field_name' => 'purchase_price',
                'old_value' => (string) $item->purchase_price,
                'new_value' => (string) round((float) $item->purchase_price + 5000, 2),
                'reason' => 'Corrected labour cost after karigar re-quote',
                'changed_by_user_id' => $this->admin->id,
            ]);
        }

        $rate = DailyRate::where('is_current', true)->where('source', 'manual')->first()
            ?? DailyRate::where('is_current', true)->first();
        if ($rate) {
            AuditLog::create([
                'entity_type' => 'rate',
                'entity_id' => $rate->id,
                'field_name' => 'rate_per_gram',
                'old_value' => (string) round((float) $rate->rate_per_gram - 120, 2),
                'new_value' => (string) $rate->rate_per_gram,
                'reason' => 'Manual morning rate update from Sarafa Bazaar',
                'changed_by_user_id' => $this->admin->id,
            ]);
        }
    }
}
