<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ItemTypeCostDefault;
use App\Models\MetalType;
use App\Models\Setting;
use App\Services\AuditLogger;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

/**
 * One place to manage shop-wide configuration: identity shown on invoices,
 * which optional modules are switched on, sales tax, and a maintenance
 * on/off switch. Everything is stored in the generic `settings` key/value
 * table (see Setting) — no new tables needed for a handful of toggles.
 */
class SystemSettingsController extends Controller
{
    /** Feature toggles this screen manages — each defaults to whatever existing shops already experience today (most "on"; the Manager permission below defaults "off", since it's a new ability nobody had before). */
    private const FEATURE_TOGGLES = ['buyback_enabled', 'bank_qr_enabled', 'exchange_enabled', 'rate_auto_refresh_enabled', 'zakat_enabled', 'bank_qr_manager_confirm_enabled'];

    /**
     * Every item type Item Entry offers (see items/create.tsx's type picker)
     * — kept here too so a default row can be shown/managed for a type even
     * before anyone has ever entered an item of that kind.
     */
    private const ITEM_TYPES = [
        'ring', 'bangle', 'necklace', 'earring', 'bracelet', 'chain', 'pendant', 'locket', 'nose_pin', 'tops', 'other',
        'biscuit', 'bar', 'nugget', 'coin', 'piece',
    ];

    public function edit()
    {
        $existingDefaults = ItemTypeCostDefault::whereIn('item_type', self::ITEM_TYPES)
            ->get()->keyBy('item_type');

        return Inertia::render('admin/settings/index', [
            'metals' => MetalType::orderBy('name')->get(['id', 'name', 'is_active']),
            'itemTypeCostDefaults' => collect(self::ITEM_TYPES)->map(fn ($type) => [
                'item_type' => $type,
                'labour_cost_default' => (float) ($existingDefaults[$type]->labour_cost_default ?? 0),
            ])->values(),
            'settings' => [
                'shop_name' => Setting::get('shop_name', config('app.name', 'Zar & Noor')),
                'shop_tagline' => Setting::get('shop_tagline', 'Jewellers — Gold & Silver'),
                'shop_address' => Setting::get('shop_address', 'Main Bazaar, Rawalpindi, Pakistan'),
                'shop_phone' => Setting::get('shop_phone', '+92 300 0000000'),
                'shop_ntn' => Setting::get('shop_ntn'),
                'shop_logo' => Setting::get('shop_logo'),

                'receipt_printer_type' => Setting::get('receipt_printer_type', 'regular'),
                'item_slip_print_width_mm' => (float) Setting::get('item_slip_print_width_mm', 80),
                'item_slip_print_height_mm' => (float) Setting::get('item_slip_print_height_mm', 0),
                'item_slip_print_padding_mm' => (float) Setting::get('item_slip_print_padding_mm', 4),

                'buyback_enabled' => Setting::bool('buyback_enabled', true),
                'bank_qr_enabled' => Setting::bool('bank_qr_enabled', true),
                'bank_qr_manager_confirm_enabled' => Setting::bool('bank_qr_manager_confirm_enabled', false),
                'exchange_enabled' => Setting::bool('exchange_enabled', true),
                'rate_auto_refresh_enabled' => Setting::bool('rate_auto_refresh_enabled', true),

                'tax_enabled' => Setting::bool('tax_enabled', false),
                'tax_rate_percent' => (float) Setting::get('tax_rate_percent', 0),

                'zakat_enabled' => Setting::bool('zakat_enabled', true),
                'zakat_rate_percent' => (float) Setting::get('zakat_rate_percent', 2.5),
                'zakat_nisab_gold_grams' => (float) Setting::get('zakat_nisab_gold_grams', 87.48),
                'zakat_nisab_silver_grams' => (float) Setting::get('zakat_nisab_silver_grams', 612.36),

                'maintenance_mode_enabled' => Setting::bool('maintenance_mode_enabled', false),
                'maintenance_message' => Setting::get('maintenance_message', ''),
            ],
        ]);
    }

    public function update(Request $request, AuditLogger $audit)
    {
        $data = $request->validate([
            'shop_name' => 'required|string|max:150',
            'shop_tagline' => 'nullable|string|max:150',
            'shop_address' => 'nullable|string|max:255',
            'shop_phone' => 'nullable|string|max:30',
            'shop_ntn' => 'nullable|string|max:30',
            'shop_logo' => 'nullable|image|max:2048',

            'receipt_printer_type' => 'required|in:regular,thermal',
            'item_slip_print_width_mm' => 'required|numeric|min:20|max:300',
            'item_slip_print_height_mm' => 'nullable|numeric|min:0|max:500',
            'item_slip_print_padding_mm' => 'required|numeric|min:0|max:50',

            'buyback_enabled' => 'required|boolean',
            'bank_qr_enabled' => 'required|boolean',
            'bank_qr_manager_confirm_enabled' => 'required|boolean',
            'exchange_enabled' => 'required|boolean',
            'rate_auto_refresh_enabled' => 'required|boolean',

            'tax_enabled' => 'required|boolean',
            'tax_rate_percent' => 'required|numeric|min:0|max:100',

            'zakat_enabled' => 'required|boolean',
            'zakat_rate_percent' => 'required|numeric|min:0|max:100',
            'zakat_nisab_gold_grams' => 'required|numeric|min:0.01',
            'zakat_nisab_silver_grams' => 'required|numeric|min:0.01',

            'maintenance_mode_enabled' => 'required|boolean',
            'maintenance_message' => 'nullable|string|max:500',

            'active_metal_ids' => 'required|array|min:1',
            'active_metal_ids.*' => ['integer', Rule::exists('metal_types', 'id')],

            'item_type_cost_defaults' => 'present|array',
            'item_type_cost_defaults.*.item_type' => ['required', 'string', Rule::in(self::ITEM_TYPES)],
            'item_type_cost_defaults.*.labour_cost_default' => 'required|numeric|min:0',
        ], [
            'active_metal_ids.required' => 'The shop must deal in at least one metal.',
            'active_metal_ids.min' => 'The shop must deal in at least one metal.',
        ]);

        $userId = $request->user()->id;

        // Metals dealt in — everything gold/silver-specific (item entry, POS,
        // buy-back, rate management) only offers a metal that's active here.
        foreach (MetalType::all(['id', 'name', 'is_active']) as $metal) {
            $shouldBeActive = in_array($metal->id, $data['active_metal_ids'], true);
            if ($metal->is_active === $shouldBeActive) {
                continue;
            }

            $audit->log(
                'settings',
                $metal->id,
                'metal_type_active',
                $metal->is_active ? '1' : '0',
                $shouldBeActive ? '1' : '0',
                $shouldBeActive ? "Dealing in {$metal->name} turned on" : "Dealing in {$metal->name} turned off",
                $userId,
            );
            $metal->update(['is_active' => $shouldBeActive]);
        }

        // Labour cost defaults per item type (FR-24/FR-25) — a making
        // charge, same category G-2 already requires logging for ("Only
        // Admin can change ... making charges. Every such action is logged
        // with no exceptions"), so each type that actually changed gets its
        // own audit row rather than one blanket entry for the whole form.
        $existingDefaults = ItemTypeCostDefault::whereIn('item_type', self::ITEM_TYPES)->get()->keyBy('item_type');
        foreach ($data['item_type_cost_defaults'] as $row) {
            $type = $row['item_type'];
            $existing = $existingDefaults[$type] ?? null;
            $oldLabour = (float) ($existing->labour_cost_default ?? 0);
            $newLabour = (float) $row['labour_cost_default'];

            if (round($oldLabour, 2) === round($newLabour, 2)) {
                continue;
            }

            ItemTypeCostDefault::updateOrCreate(
                ['item_type' => $type],
                ['labour_cost_default' => $newLabour, 'updated_by_user_id' => $userId],
            );

            $audit->log(
                'settings',
                0,
                'item_type_cost_default',
                "Rs {$oldLabour}",
                "Rs {$newLabour}",
                ucfirst(str_replace('_', ' ', $type)).' default labour cost updated',
                $userId,
            );
        }

        // Maintenance mode and sales tax have real, shop-wide consequences
        // the moment they're saved — worth an audit row, same as any other
        // protected override — so read the old values before overwriting them.
        $wasMaintenance = Setting::bool('maintenance_mode_enabled');
        $wasManagerConfirm = Setting::bool('bank_qr_manager_confirm_enabled', false);
        $wasTaxEnabled = Setting::bool('tax_enabled', false);
        $wasTaxRate = (float) Setting::get('tax_rate_percent', 0);
        $wasZakatRate = (float) Setting::get('zakat_rate_percent', 2.5);
        $wasNisabGold = (float) Setting::get('zakat_nisab_gold_grams', 87.48);
        $wasNisabSilver = (float) Setting::get('zakat_nisab_silver_grams', 612.36);

        foreach ([
            'shop_name', 'shop_tagline', 'shop_address', 'shop_phone', 'shop_ntn',
            'tax_rate_percent', 'zakat_rate_percent', 'zakat_nisab_gold_grams', 'zakat_nisab_silver_grams',
            'maintenance_message', 'receipt_printer_type',
            'item_slip_print_width_mm', 'item_slip_print_height_mm', 'item_slip_print_padding_mm',
        ] as $key) {
            Setting::put($key, $data[$key] ?? '', $userId);
        }

        foreach ([...self::FEATURE_TOGGLES, 'tax_enabled', 'maintenance_mode_enabled'] as $key) {
            Setting::put($key, $data[$key], $userId);
        }

        // A logo is only ever replaced when a new file is actually chosen —
        // same as the Bank QR image, there's no "remove" input here, so an
        // absent file must never blank out the one already saved.
        if ($request->hasFile('shop_logo')) {
            $file = $request->file('shop_logo');
            Setting::put('shop_logo', 'data:'.$file->getMimeType().';base64,'.base64_encode($file->get()), $userId);
        }

        if ($wasMaintenance !== $data['maintenance_mode_enabled']) {
            $audit->log(
                'settings',
                0,
                'maintenance_mode_enabled',
                $wasMaintenance ? '1' : '0',
                $data['maintenance_mode_enabled'] ? '1' : '0',
                $data['maintenance_mode_enabled'] ? 'Maintenance mode turned on' : 'Maintenance mode turned off',
                $userId,
            );
        }

        if ($wasManagerConfirm !== $data['bank_qr_manager_confirm_enabled']) {
            $audit->log(
                'settings',
                0,
                'bank_qr_manager_confirm_enabled',
                $wasManagerConfirm ? '1' : '0',
                $data['bank_qr_manager_confirm_enabled'] ? '1' : '0',
                $data['bank_qr_manager_confirm_enabled']
                    ? 'Managers granted Bank QR payment confirmation'
                    : 'Managers no longer allowed to confirm Bank QR payments',
                $userId,
            );
        }

        if ($wasTaxEnabled !== $data['tax_enabled'] || round($wasTaxRate, 2) !== round($data['tax_rate_percent'], 2)) {
            $audit->log(
                'settings',
                0,
                'tax_rate_percent',
                $wasTaxEnabled ? "{$wasTaxRate}%" : 'off',
                $data['tax_enabled'] ? "{$data['tax_rate_percent']}%" : 'off',
                'Sales tax setting updated',
                $userId,
            );
        }

        if (round($wasZakatRate, 2) !== round($data['zakat_rate_percent'], 2)
            || round($wasNisabGold, 2) !== round($data['zakat_nisab_gold_grams'], 2)
            || round($wasNisabSilver, 2) !== round($data['zakat_nisab_silver_grams'], 2)) {
            $audit->log(
                'settings',
                0,
                'zakat_rate_percent',
                "{$wasZakatRate}% · {$wasNisabGold}g gold · {$wasNisabSilver}g silver",
                "{$data['zakat_rate_percent']}% · {$data['zakat_nisab_gold_grams']}g gold · {$data['zakat_nisab_silver_grams']}g silver",
                'Zakat rate/Nisab setting updated',
                $userId,
            );
        }

        return back()->with('success', 'Settings saved.');
    }
}
