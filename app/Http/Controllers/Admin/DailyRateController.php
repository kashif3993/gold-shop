<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\DailyRate;
use App\Models\MetalType;
use Illuminate\Http\Request;
use Inertia\Inertia;

class DailyRateController extends Controller
{
    /** Full, filterable history of every rate ever stored — not just the current one. */
    public function index(Request $request)
    {
        $filters = $request->only(['metal_type_id', 'purity_id', 'source', 'date_from', 'date_to']);

        $rates = DailyRate::with(['metalType:id,name', 'purity:id,name', 'enteredBy:id,username'])
            ->when($filters['metal_type_id'] ?? null, fn ($q, $v) => $q->where('metal_type_id', $v))
            ->when($filters['purity_id'] ?? null, fn ($q, $v) => $q->where('purity_id', $v))
            ->when($filters['source'] ?? null, fn ($q, $v) => $q->where('source', $v))
            ->when($filters['date_from'] ?? null, fn ($q, $v) => $q->whereDate('rate_date', '>=', $v))
            ->when($filters['date_to'] ?? null, fn ($q, $v) => $q->whereDate('rate_date', '<=', $v))
            ->orderByDesc('fetched_at')
            ->paginate(30)
            ->withQueryString();

        return Inertia::render('admin/daily-rates/index', [
            'rates' => $rates,
            'filters' => $filters,
            'metals' => MetalType::with('purities')->orderBy('name')->get(),
        ]);
    }
}
