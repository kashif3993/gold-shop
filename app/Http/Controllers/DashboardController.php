<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\DailyRate;
use App\Models\Item;
use App\Models\MetalType;
use App\Models\Party;
use App\Models\Purity;
use App\Models\User;
use App\Services\ReportService;
use Illuminate\Support\Collection;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __invoke(ReportService $reports): Response
    {
        // The purest active purity of each metal the shop currently deals in
        // (Admin → Settings) stands in as that metal's headline "spot" rate —
        // same convention ReportService::rateHistory() uses, so the dashboard
        // card and the Reports chart never disagree.
        $benchmarkPurityByMetal = Purity::with('metalType:id,name')
            ->where('is_active', true)
            ->whereHas('metalType', fn ($q) => $q->where('is_active', true))
            ->orderByDesc('fineness_percent')
            ->get()
            ->groupBy(fn (Purity $p) => $p->metalType->name ?? 'Unknown')
            ->map(fn (Collection $purities) => $purities->first());

        $currentRateByPurityId = DailyRate::where('is_current', true)->get()->keyBy('purity_id');

        $ratesByMetal = $benchmarkPurityByMetal->map(
            fn (Purity $p) => $currentRateByPurityId->get($p->id)?->rate_per_gram
        );

        $metalBreakdown = MetalType::withCount('purities')->orderBy('name')->get()
            ->map(fn (MetalType $metal) => [
                'name' => $metal->name,
                'purity_count' => $metal->purities_count,
            ]);

        $totalPurities = $metalBreakdown->sum('purity_count');

        // Real per-purity stock counts (gold and silver alike) for the
        // "Purity Configuration" panel — previously this was never sent, so
        // the screen silently fell back to hardcoded zeros.
        $itemCountByPurity = Item::where('status', 'in_stock')
            ->selectRaw('purity_id, count(*) as c')
            ->groupBy('purity_id')
            ->pluck('c', 'purity_id');

        $purities = Purity::with('metalType:id,name')
            ->where('is_active', true)
            ->orderBy('metal_type_id')
            ->orderByDesc('fineness_percent')
            ->get()
            ->map(fn (Purity $p) => [
                'label' => $p->name,
                'metal' => $p->metalType->name ?? 'Unknown',
                'item_count' => (int) ($itemCountByPurity->get($p->id) ?? 0),
                'metal_color' => strtolower($p->metalType->name ?? '') === 'silver' ? '#94a3b8' : '#d4a017',
            ]);

        $recentActivity = AuditLog::with('changedBy')
            ->latest('changed_at')
            ->limit(5)
            ->get()
            ->map(fn (AuditLog $log) => [
                'field_name' => $log->field_name,
                'reason' => $log->reason,
                'changed_by' => $log->changedBy?->username,
                'changed_at' => $log->changed_at?->diffForHumans(),
            ]);

        return Inertia::render('dashboard', [
            'stats' => [
                'items_in_stock' => Item::where('status', 'in_stock')->count(),
                'total_parties' => Party::count(),
                'total_users' => User::where('is_active', true)->count(),
                'current_gold_rate' => $ratesByMetal->get('Gold'),
                'current_silver_rate' => $ratesByMetal->get('Silver'),
            ],
            // Which metals the shop currently deals in — hides the other
            // metal's rate card/chart option outright, rather than showing
            // it as "Not set" (that would read as missing data, not "we
            // don't deal in this").
            'dealsIn' => [
                'gold' => MetalType::where('name', 'Gold')->value('is_active') ?? false,
                'silver' => MetalType::where('name', 'Silver')->value('is_active') ?? false,
            ],
            'metalBreakdown' => $metalBreakdown,
            'purities' => $purities,
            'totalPurities' => $totalPurities,
            'recentActivity' => $recentActivity,
            // Last 7 days, one line per metal — same shape/source as the Reports rate-history chart.
            'rateHistory' => $reports->rateHistory(now()->subDays(6)->toDateString(), now()->toDateString()),
        ]);
    }
}
