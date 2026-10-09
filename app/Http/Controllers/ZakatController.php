<?php

namespace App\Http\Controllers;

use App\Models\MetalType;
use App\Models\Purity;
use App\Models\Setting;
use App\Services\GoldRateService;
use App\Services\ZakatService;
use Inertia\Inertia;

class ZakatController extends Controller
{
    public function index(GoldRateService $rates, ZakatService $zakat)
    {
        return Inertia::render('zakat/index', [
            'metals' => MetalType::active()->get(['id', 'name']),
            'purities' => Purity::whereHas('metalType', fn ($q) => $q->where('is_active', true))
                ->where('is_active', true)
                ->get(['id', 'name', 'metal_type_id']),
            // Keyed by purity_id — same shape POS/Buy-Back already share, so
            // the manual calculator can price any purity instantly, client
            // side, without a round trip (nothing here is saved, so there's
            // no server-authority concern like there is at checkout).
            'currentRates' => $rates->currentRates()->map(fn ($r) => (float) $r->rate_per_gram),
            'ratePercent' => $zakat->ratePercent(),
            'nisab' => $zakat->nisab(),
            'shopStock' => $zakat->shopStock(),
            'nisabGrams' => [
                'gold' => (float) Setting::get('zakat_nisab_gold_grams', 87.48),
                'silver' => (float) Setting::get('zakat_nisab_silver_grams', 612.36),
            ],
        ]);
    }
}
