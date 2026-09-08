<?php

namespace App\Http\Controllers;

use App\Models\Item;
use App\Models\MetalType;
use App\Models\Party;
use App\Models\Purity;
use App\Services\ItemTagService;
use Illuminate\Http\Request;
use Inertia\Inertia;

class ItemController extends Controller
{
    public function create()
    {
        return Inertia::render('items/create', [
            'metals'   => MetalType::all(),
            'purities' => Purity::all(),
            'parties'  => Party::all(),
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'item_code'               => 'nullable|string|unique:items,item_code',
            'item_type'               => 'required|string',
            'metal_type_id'           => 'required|exists:metal_types,id',
            'purity_id'               => 'required|exists:purities,id',
            'gross_weight_grams'      => 'required|numeric|min:0.001',
            'stone_weight_grams'      => 'required|numeric|min:0',
            'cutting_loss_grams'      => 'required|numeric|min:0',
            'net_weight_grams'        => 'nullable|numeric|min:0',
            'labour_cost'             => 'required|numeric|min:0',
            'polish_cost'             => 'required|numeric|min:0',
            'purchase_rate_per_gram'  => 'required|numeric|min:0',
            'purchase_price'          => 'nullable|numeric|min:0',
            'source_party_id'         => 'nullable|exists:parties,id',
            'date_received'           => 'required|date',
        ], [
            'gross_weight_grams.min' => 'Gross weight must be greater than 0.',
        ]);

        // Generate structured item code if not provided
        if (empty($validated['item_code'])) {
            $metal = MetalType::find($validated['metal_type_id']);
            $validated['item_code'] = ItemTagService::generateItemCode(
                $metal?->name ?? 'Item',
                $validated['item_type']
            );
        }

        $purity    = Purity::find($validated['purity_id']);
        $itemType  = strtolower((string) ($validated['item_type'] ?? ''));
        if ($itemType === 'ring' && $purity && preg_match('/^24\s*k$/i', trim((string) $purity->name)) === 1) {
            return back()->withErrors(['purity_id' => 'Ring items cannot use 24K purity.'])->withInput();
        }

        $validated['status']              = 'in_stock';
        $validated['created_by_user_id']  = auth()->id();
        $validated['source_party_id']     = $validated['source_party_id'] ?: null;

        // net_weight_grams is a generated column in MySQL — omit from explicit insert
        unset($validated['net_weight_grams']);

        $item = Item::create($validated);

        // Generate and save QR + barcode payload after creation (we now have the ID)
        $item->qr_payload = ItemTagService::generateQrPayload($item);
        $item->saveQuietly();

        return redirect()->route('items.tag', $item)
            ->with('success', 'Item created successfully.');
    }

    /**
     * Show the printable tag page for an item.
     */
    public function tag(Item $item)
    {
        $item->load(['metalType', 'purity', 'sourceParty']);

        return Inertia::render('items/tag', [
            'item' => $item,
        ]);
    }

    public function edit(Item $item)
    {
        return Inertia::render('items/edit', [
            'item'     => $item->load(['metalType', 'purity', 'sourceParty']),
            'metals'   => MetalType::all(),
            'purities' => Purity::all(),
            'parties'  => Party::all(),
        ]);
    }

    public function update(Request $request, Item $item)
    {
        $validated = $request->validate([
            'item_code'              => 'required|string|unique:items,item_code,' . $item->id,
            'item_type'              => 'required|string',
            'metal_type_id'          => 'required|exists:metal_types,id',
            'purity_id'              => 'required|exists:purities,id',
            'gross_weight_grams'     => 'required|numeric|min:0.001',
            'stone_weight_grams'     => 'required|numeric|min:0',
            'cutting_loss_grams'     => 'required|numeric|min:0',
            'net_weight_grams'       => 'nullable|numeric|min:0',
            'labour_cost'            => 'required|numeric|min:0',
            'polish_cost'            => 'required|numeric|min:0',
            'purchase_rate_per_gram' => 'required|numeric|min:0',
            'purchase_price'         => 'nullable|numeric|min:0',
            'source_party_id'        => 'nullable|exists:parties,id',
            'date_received'          => 'required|date',
            'status'                 => 'required|in:in_stock,sold,bought_back',
        ], [
            'gross_weight_grams.min' => 'Gross weight must be greater than 0.',
        ]);

        $validated['source_party_id'] = $validated['source_party_id'] ?: null;

        $purity   = Purity::find($validated['purity_id']);
        $itemType = strtolower((string) ($validated['item_type'] ?? ''));
        if ($itemType === 'ring' && $purity && preg_match('/^24\s*k$/i', trim((string) $purity->name)) === 1) {
            return back()->withErrors(['purity_id' => 'Ring items cannot use 24K purity.'])->withInput();
        }

        // net_weight_grams is a generated column — omit from explicit update
        unset($validated['net_weight_grams']);

        $item->update($validated);

        // Regenerate QR payload if item details changed
        $item->qr_payload = ItemTagService::generateQrPayload($item->fresh(['metalType', 'purity']));
        $item->saveQuietly();

        return redirect()->route('inventory.index')
            ->with('success', 'Item updated successfully.');
    }

    public function destroy(Item $item)
    {
        $item->delete();

        return redirect()->route('inventory.index')
            ->with('success', 'Item deleted successfully.');
    }
}
