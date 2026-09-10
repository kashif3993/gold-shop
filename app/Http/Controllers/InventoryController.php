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
        ]);
    }
}
