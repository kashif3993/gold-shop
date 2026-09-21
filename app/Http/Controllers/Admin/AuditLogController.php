<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use Illuminate\Http\Request;
use Inertia\Inertia;

/**
 * The read-only view of everything AuditLogger has ever recorded — rate
 * edits, price/weight overrides, discounts, purity changes. The writes
 * already happen throughout the app (see App\Services\AuditLogger); this is
 * just the first place any of it can actually be seen.
 */
class AuditLogController extends Controller
{
    private const ENTITY_TYPES = [
        'item_price', 'sale_line_price', 'rate', 'purity',
        'buyback_deduction', 'transaction_weight', 'discount',
    ];

    public function index(Request $request)
    {
        $filters = $request->only(['entity_type', 'date_from', 'date_to']);

        $logs = AuditLog::with('changedBy:id,username')
            ->when($filters['entity_type'] ?? null, fn ($q, $v) => $q->where('entity_type', $v))
            ->when($filters['date_from'] ?? null, fn ($q, $v) => $q->whereDate('changed_at', '>=', $v))
            ->when($filters['date_to'] ?? null, fn ($q, $v) => $q->whereDate('changed_at', '<=', $v))
            ->orderByDesc('changed_at')
            ->paginate(30)
            ->withQueryString();

        return Inertia::render('admin/audit-log/index', [
            'logs' => $logs,
            'filters' => $filters,
            'entityTypes' => self::ENTITY_TYPES,
        ]);
    }
}
