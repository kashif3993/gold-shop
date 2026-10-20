<?php

namespace App\Http\Controllers;

use App\Models\DailyRate;
use App\Models\Item;
use App\Models\ItemTypeCostDefault;
use App\Models\MetalType;
use App\Models\Party;
use App\Models\Purity;
use App\Models\Setting;
use App\Services\AuditLogger;
use App\Services\ItemTagService;
use Illuminate\Http\Request;
use Inertia\Inertia;

class ItemController extends Controller
{
    public function __construct(private AuditLogger $audit) {}

    /** Current effective rate per purity_id, for the "use today's rate" helper. */
    private function currentRates()
    {
        return DailyRate::where('is_current', true)->pluck('rate_per_gram', 'purity_id');
    }

    /** Shop identity + printer sizing for the "Save & Print" weight/value slip (see items/slip.tsx, item-entry-print-slip.tsx). */
    private function printContext(): array
    {
        return [
            'shop' => [
                'name' => Setting::get('shop_name', config('app.name', 'Zar & Noor')),
                'address' => Setting::get('shop_address'),
                'phone' => Setting::get('shop_phone'),
                'logo' => Setting::get('shop_logo'),
            ],
            'printerType' => Setting::get('receipt_printer_type', 'regular'),
            'printDimensions' => [
                'widthMm' => (float) Setting::get('item_slip_print_width_mm', 80),
                'heightMm' => (float) Setting::get('item_slip_print_height_mm', 0),
                'paddingMm' => (float) Setting::get('item_slip_print_padding_mm', 4),
            ],
        ];
    }

    /**
     * The item shape "Save & Print" / "Update & Print" flash back to the
     * page — printed in place client-side (see item-entry-print-slip.tsx)
     * without navigating anywhere. Needs metalType/purity/sourceParty loaded.
     */
    private function printSlipPayload(Item $item): array
    {
        return [
            'item_code' => $item->item_code,
            'item_type' => $item->item_type,
            'date_received' => $item->date_received->toDateString(),
            'status' => $item->status,
            'gross_weight_grams' => $item->gross_weight_grams,
            'stone_weight_grams' => $item->stone_weight_grams,
            'cutting_loss_grams' => $item->cutting_loss_grams,
            'polish_weight_grams' => $item->polish_weight_grams,
            'net_weight_grams' => $item->net_weight_grams,
            'labour_cost' => $item->labour_cost,
            'purchase_rate_per_gram' => $item->purchase_rate_per_gram,
            'purchase_price' => $item->purchase_price,
            'metal_type' => $item->metalType ? ['name' => $item->metalType->name] : null,
            'purity' => $item->purity ? ['name' => $item->purity->name] : null,
            'source_party' => $item->sourceParty ? ['name' => $item->sourceParty->name] : null,
        ];
    }

    public function create()
    {
        return Inertia::render('items/create', array_merge([
            'metals' => MetalType::active()->get(),
            'purities' => Purity::all(),
            'parties' => Party::all(),
            'currentRates' => $this->currentRates(),
            'labourCostDefaults' => ItemTypeCostDefault::pluck('labour_cost_default', 'item_type'),
        ], $this->printContext()));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'item_code' => 'nullable|string|unique:items,item_code',
            'item_type' => 'required|string',
            'metal_type_id' => 'required|exists:metal_types,id',
            'purity_id' => 'required|exists:purities,id',
            'gross_weight_grams' => 'required|numeric|min:0.001',
            'stone_weight_grams' => 'required|numeric|min:0',
            'cutting_loss_grams' => 'required|numeric|min:0',
            'polish_weight_grams' => 'required|numeric|min:0',
            'net_weight_grams' => 'nullable|numeric|min:0',
            'labour_cost' => 'required|numeric|min:0',
            'purchase_rate_per_gram' => 'required|numeric|min:0',
            'purchase_price' => 'nullable|numeric|min:0',
            'source_party_id' => 'nullable|exists:parties,id',
            'date_received' => 'required|date',
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

        $purity = Purity::find($validated['purity_id']);
        $itemType = strtolower((string) ($validated['item_type'] ?? ''));
        if ($itemType === 'ring' && $purity && preg_match('/^24\s*k$/i', trim((string) $purity->name)) === 1) {
            return back()->withErrors(['purity_id' => 'Ring items cannot use 24K purity.'])->withInput();
        }

        $validated['status'] = 'in_stock';
        $validated['created_by_user_id'] = auth()->id();
        $validated['source_party_id'] = $validated['source_party_id'] ?: null;

        // net_weight_grams is a generated column in MySQL — omit from explicit insert
        unset($validated['net_weight_grams']);

        $item = Item::create($validated);

        // Generate and save QR + barcode payload after creation (we now have the ID)
        $item->qr_payload = ItemTagService::generateQrPayload($item);
        $item->saveQuietly();

        // Stays on the Create page rather than navigating away — a shopkeeper
        // entering a batch of items shouldn't be bounced to a different screen
        // after every single one. The frontend clears the form itself (and, for
        // "Save & Print", prints this item in place) once it sees this flash.
        $item->load(['metalType', 'purity', 'sourceParty']);

        return redirect()->route('items.create')
            ->with('success', "Item {$item->item_code} created successfully.")
            ->with('createdItem', $this->printSlipPayload($item));
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

    /**
     * Show the printable weight/value slip for an item — the Gram / Tola /
     * Masha / Ratti / Point breakdown of its Net, Stone and Polish weights,
     * plus costing. Reached from Edit's "Update & Print" (Create prints the
     * same slip in place — see store() — rather than navigating here).
     */
    public function slip(Item $item)
    {
        $item->load(['metalType', 'purity', 'sourceParty']);

        return Inertia::render('items/slip', array_merge([
            'item' => $item,
        ], $this->printContext()));
    }

    public function edit(Item $item)
    {
        // Active metals, plus this item's own metal even if it's since been
        // switched off — editing an existing item must never lose its metal
        // from the dropdown.
        $metals = MetalType::where('is_active', true)
            ->orWhere('id', $item->metal_type_id)
            ->get();

        return Inertia::render('items/edit', array_merge([
            'item' => $item->load(['metalType', 'purity', 'sourceParty']),
            'metals' => $metals,
            'purities' => Purity::all(),
            'parties' => Party::all(),
            'currentRates' => $this->currentRates(),
        ], $this->printContext()));
    }

    public function update(Request $request, Item $item)
    {
        $validated = $request->validate([
            'item_code' => 'required|string|unique:items,item_code,'.$item->id,
            'item_type' => 'required|string',
            'metal_type_id' => 'required|exists:metal_types,id',
            'purity_id' => 'required|exists:purities,id',
            'gross_weight_grams' => 'required|numeric|min:0.001',
            'stone_weight_grams' => 'required|numeric|min:0',
            'cutting_loss_grams' => 'required|numeric|min:0',
            'polish_weight_grams' => 'required|numeric|min:0',
            'net_weight_grams' => 'nullable|numeric|min:0',
            'labour_cost' => 'required|numeric|min:0',
            'purchase_rate_per_gram' => 'required|numeric|min:0',
            'purchase_price' => 'nullable|numeric|min:0',
            'source_party_id' => 'nullable|exists:parties,id',
            'date_received' => 'required|date',
            'status' => 'required|in:in_stock,sold,bought_back',
            'edit_reason' => 'nullable|string|max:255',
        ], [
            'gross_weight_grams.min' => 'Gross weight must be greater than 0.',
        ]);

        $validated['source_party_id'] = $validated['source_party_id'] ?: null;

        $purity = Purity::find($validated['purity_id']);
        $itemType = strtolower((string) ($validated['item_type'] ?? ''));
        if ($itemType === 'ring' && $purity && preg_match('/^24\s*k$/i', trim((string) $purity->name)) === 1) {
            return back()->withErrors(['purity_id' => 'Ring items cannot use 24K purity.'])->withInput();
        }

        $reason = trim((string) ($validated['edit_reason'] ?? '')) ?: 'Item details updated via Edit screen';
        unset($validated['edit_reason']);

        // Snapshot the protected fields before they change, for the audit trail below.
        $original = $item->only(['purchase_rate_per_gram', 'purchase_price', 'gross_weight_grams', 'stone_weight_grams', 'cutting_loss_grams', 'polish_weight_grams']);

        // net_weight_grams is a generated column — omit from explicit update
        unset($validated['net_weight_grams']);

        $item->update($validated);

        $auditedFields = [
            'purchase_rate_per_gram' => 'item_price',
            'purchase_price' => 'item_price',
            'gross_weight_grams' => 'transaction_weight',
            'stone_weight_grams' => 'transaction_weight',
            'cutting_loss_grams' => 'transaction_weight',
            'polish_weight_grams' => 'transaction_weight',
        ];
        foreach ($auditedFields as $field => $entityType) {
            $this->audit->logIfChanged($entityType, $item->id, $field, $original[$field], $item->$field, $reason);
        }

        // Regenerate QR payload if item details changed
        $item->qr_payload = ItemTagService::generateQrPayload($item->fresh(['metalType', 'purity']));
        $item->saveQuietly();

        // Stays on the Edit page rather than navigating away — same reasoning
        // as Create's "Save & Print" (see store()): the frontend clears the
        // "reason for change" note itself (and, for "Update & Print", prints
        // this item in place) once it sees this flash.
        $item->load(['metalType', 'purity', 'sourceParty']);

        return redirect()->route('items.edit', $item)
            ->with('success', "Item {$item->item_code} updated successfully.")
            ->with('createdItem', $this->printSlipPayload($item));
    }

    public function destroy(Item $item)
    {
        $item->delete();

        return redirect()->route('inventory.index')
            ->with('success', 'Item deleted successfully.');
    }
}
