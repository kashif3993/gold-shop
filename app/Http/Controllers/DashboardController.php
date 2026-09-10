<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\DailyRate;
use App\Models\Item;
use App\Models\MetalType;
use App\Models\Party;
use App\Models\User;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __invoke(): Response
    {
        $currentGoldRate = DailyRate::whereHas('metalType', fn ($q) => $q->where('name', 'Gold'))
            ->where('is_current', true)
            ->latest('fetched_at')
            ->first();

        $metalBreakdown = MetalType::withCount('purities')->orderBy('name')->get()
            ->map(fn (MetalType $metal) => [
                'name' => $metal->name,
                'purity_count' => $metal->purities_count,
            ]);

        $totalPurities = $metalBreakdown->sum('purity_count');

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
                'current_gold_rate' => $currentGoldRate?->rate_per_gram,
            ],
            'metalBreakdown' => $metalBreakdown,
            'totalPurities' => $totalPurities,
            'recentActivity' => $recentActivity,
        ]);
    }
}
