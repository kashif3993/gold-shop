<?php

namespace App\Http\Controllers;

use App\Models\Item;
use App\Models\MetalType;
use App\Models\Party;
use App\Models\Purity;
use Illuminate\Http\Request;
use Inertia\Inertia;

class InventoryController extends Controller
{
    public function index(Request $request)
    {
        $query = Item::with(['metalType', 'purity', 'sourceParty'])
            ->orderBy('created_at', 'desc');

        if ($request->filled('metal_type_id')) {
            $query->where('metal_type_id', $request->metal_type_id);
        }

        if ($request->filled('purity_id')) {
            $query->where('purity_id', $request->purity_id);
        }

        if ($request->filled('item_type')) {
            $query->where('item_type', $request->item_type);
        }

        if ($request->filled('date')) {
            $query->whereDate('date_received', $request->date);
        }

        if ($request->filled('source_party_id')) {
            $query->where('source_party_id', $request->source_party_id);
        }

        $items = $query->paginate(20)->withQueryString();

        return Inertia::render('inventory/index', [
            'items' => $items,
            'filters' => $request->only(['metal_type_id', 'purity_id', 'item_type', 'date', 'source_party_id']),
            'metals' => MetalType::all(),
            'purities' => Purity::all(),
            'parties' => Party::whereHas('sourcedItems')->orderBy('name')->get(['id', 'name']),
            'stockSummary' => $this->stockSummary(),
        ]);
    }

    /**
     * Total gold/silver physically on hand right now, split into "ready to
     * sell" (in_stock — new jewelry + bullion) vs "bought-back scrap" (taken
     * in via POS exchange or Buy-Back, not yet melted/reprocessed). Both are
     * real weight sitting in the shop, so both count toward what the owner
     * actually holds — kept separate because only `in_stock` is sellable as-is.
     */
    private function stockSummary()
    {
        $rows = Item::query()
            ->whereIn('status', ['in_stock', 'bought_back'])
            ->selectRaw('metal_type_id, status, SUM(net_weight_grams) as total_grams, COUNT(*) as item_count')
            ->groupBy('metal_type_id', 'status')
            ->get();

        return MetalType::all()->map(function (MetalType $metal) use ($rows) {
            $forMetal = $rows->where('metal_type_id', $metal->id);
            $inStock = $forMetal->firstWhere('status', 'in_stock');
            $boughtBack = $forMetal->firstWhere('status', 'bought_back');

            return [
                'metal' => $metal->name,
                'in_stock_grams' => (float) ($inStock->total_grams ?? 0),
                'in_stock_count' => (int) ($inStock->item_count ?? 0),
                'bought_back_grams' => (float) ($boughtBack->total_grams ?? 0),
                'bought_back_count' => (int) ($boughtBack->item_count ?? 0),
            ];
        })->filter(fn ($row) => $row['in_stock_count'] > 0 || $row['bought_back_count'] > 0)->values();
    }
}
