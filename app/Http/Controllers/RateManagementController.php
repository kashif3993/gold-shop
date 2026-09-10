<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\MetalType;
use App\Models\Purity;
use App\Models\RateAdjustmentSetting;
use App\Models\RateFetchLog;
use App\Models\Setting;
use App\Services\GoldRateService;
use Illuminate\Http\Request;
use Inertia\Inertia;

class RateManagementController extends Controller
{
    public function __construct(private GoldRateService $rates) {}

    public function index()
    {
        $current = $this->rates->currentRates();

        $rows = Purity::with('metalType:id,name')
            ->where('is_active', true)
            ->orderBy('metal_type_id')
            ->orderByDesc('fineness_percent')
            ->get()
            ->map(function (Purity $purity) use ($current) {
                $rate = $current->get($purity->id);

                return [
                    'purity_id' => $purity->id,
                    'purity_name' => $purity->name,
                    'metal_type_id' => $purity->metal_type_id,
                    'metal_name' => $purity->metalType->name ?? '',
                    'fineness_percent' => (float) $purity->fineness_percent,
                    'raw' => $rate ? (float) $rate->api_raw_rate_per_gram : null,
                    'adjustment_type' => $rate?->adjustment_type_used,
                    'adjustment_value' => $rate ? (float) $rate->adjustment_value_used : null,
                    'effective' => $rate ? (float) $rate->rate_per_gram : null,
                    'source' => $rate?->source,
                    'fetched_at' => optional($rate?->fetched_at)->toIso8601String(),
                    'is_stale' => (bool) ($rate?->is_stale),
                ];
            });

        $shopDefault = RateAdjustmentSetting::where('is_shop_default', true)->first();

        $overrides = RateAdjustmentSetting::with(['metalType:id,name', 'purity:id,name'])
            ->where('is_shop_default', false)
            ->get()
            ->map(fn (RateAdjustmentSetting $s) => [
                'id' => $s->id,
                'scope' => $s->purity_id ? 'purity' : 'metal',
                'metal_name' => $s->metalType->name ?? null,
                'purity_name' => $s->purity->name ?? null,
                'adjustment_type' => $s->adjustment_type,
                'adjustment_value' => (float) $s->adjustment_value,
            ]);

        return Inertia::render('rate-management/index', [
            'rates' => $rows,
            'metals' => MetalType::with(['purities' => fn ($q) => $q->where('is_active', true)])->get(),
            'shopDefault' => $shopDefault ? [
                'id' => $shopDefault->id,
                'adjustment_type' => $shopDefault->adjustment_type,
                'adjustment_value' => (float) $shopDefault->adjustment_value,
            ] : null,
            'overrides' => $overrides,
            'fetchLog' => RateFetchLog::latest('attempted_at')->limit(15)->get(),
            'feedStale' => $this->rates->isFeedStale(),
            'staleAfterHours' => $this->rates->staleAfterHours(),
            'fx' => $this->rates->fxState(),
        ]);
    }

    /** Set the USD→PKR rate (manual value + optional auto-fetch), then re-fetch. */
    public function saveFx(Request $request)
    {
        $data = $request->validate([
            'usd_pkr' => 'required|numeric|min:1|max:100000',
            'auto' => 'boolean',
        ]);

        $old = Setting::get('usd_pkr', config('services.gold_api.usd_pkr'));

        Setting::put('usd_pkr', $data['usd_pkr'], auth()->id());
        Setting::put('usd_pkr_auto', (bool) ($data['auto'] ?? false), auth()->id());

        AuditLog::create([
            'entity_type' => 'rate',
            'entity_id' => 0,
            'field_name' => 'usd_pkr',
            'old_value' => (string) $old,
            'new_value' => $data['usd_pkr'] . (($data['auto'] ?? false) ? ' (auto)' : ''),
            'reason' => 'USD→PKR rate updated',
            'changed_by_user_id' => auth()->id(),
        ]);

        // Re-fetch so the change takes effect on the current rates immediately.
        $log = $this->rates->refreshFromApi(auth()->id());

        return $log->success
            ? back()->with('success', 'USD→PKR updated and rates refreshed.')
            : back()->with('error', 'USD→PKR saved, but the rate refresh failed — it will apply on the next successful fetch.');
    }

    /** Pull the spot rate now. */
    public function refresh()
    {
        $log = $this->rates->refreshFromApi(auth()->id());

        return $log->success
            ? back()->with('success', $log->response_summary)
            : back()->with('error', $log->response_summary . ' — kept the last cached rate.');
    }

    /** Enter today's pure-metal spot manually; every purity is derived from it. */
    public function saveManual(Request $request)
    {
        $data = $request->validate([
            'gold_spot' => 'nullable|numeric|min:1',
            'silver_spot' => 'nullable|numeric|min:1',
        ]);

        if (empty($data['gold_spot']) && empty($data['silver_spot'])) {
            return back()->with('error', 'Enter a gold or a silver rate.');
        }

        $bases = [];
        if (! empty($data['gold_spot'])) {
            $bases['Gold'] = (float) $data['gold_spot'];
        }
        if (! empty($data['silver_spot'])) {
            $bases['Silver'] = (float) $data['silver_spot'];
        }

        $count = $this->rates->applyManual($bases, auth()->id());

        AuditLog::create([
            'entity_type' => 'rate',
            'entity_id' => 0,
            'field_name' => 'manual_spot',
            'old_value' => null,
            'new_value' => collect($bases)->map(fn ($v, $k) => "{$k} {$v}/g")->implode(', '),
            'reason' => 'Manual rate entry',
            'changed_by_user_id' => auth()->id(),
        ]);

        return back()->with('success', "Updated {$count} rates from the manual entry.");
    }

    /** Create / update a rate adjustment (shop default, per metal, or per purity). */
    public function saveAdjustment(Request $request)
    {
        $data = $request->validate([
            'scope' => 'required|in:shop,metal,purity',
            'metal_type_id' => 'nullable|required_if:scope,metal|exists:metal_types,id',
            'purity_id' => 'nullable|required_if:scope,purity|exists:purities,id',
            'adjustment_type' => 'required|in:amount,percent',
            'adjustment_value' => 'required|numeric|between:-100000,100000',
            'reason' => 'nullable|string|max:255',
        ]);

        $lookup = match ($data['scope']) {
            'shop' => ['is_shop_default' => true],
            'metal' => ['is_shop_default' => false, 'metal_type_id' => $data['metal_type_id'], 'purity_id' => null],
            'purity' => ['is_shop_default' => false, 'purity_id' => $data['purity_id']],
        };

        $existing = RateAdjustmentSetting::where($lookup)->first();
        $oldLabel = $existing
            ? "{$existing->adjustment_type} {$existing->adjustment_value}"
            : 'none';

        $setting = RateAdjustmentSetting::updateOrCreate($lookup, [
            'metal_type_id' => $data['scope'] === 'purity'
                ? Purity::find($data['purity_id'])?->metal_type_id
                : ($data['metal_type_id'] ?? null),
            'adjustment_type' => $data['adjustment_type'],
            'adjustment_value' => $data['adjustment_value'],
            'updated_by_user_id' => auth()->id(),
        ]);

        $this->rates->reapplyAdjustments();

        AuditLog::create([
            'entity_type' => 'rate',
            'entity_id' => $setting->id,
            'field_name' => "adjustment.{$data['scope']}",
            'old_value' => $oldLabel,
            'new_value' => "{$data['adjustment_type']} {$data['adjustment_value']}",
            'reason' => $data['reason'] ?: 'Rate adjustment updated',
            'changed_by_user_id' => auth()->id(),
        ]);

        return back()->with('success', 'Rate adjustment saved and applied to current rates.');
    }

    public function deleteAdjustment(RateAdjustmentSetting $adjustment)
    {
        if ($adjustment->is_shop_default) {
            return back()->with('error', 'The shop default cannot be deleted — set it to 0 instead.');
        }

        AuditLog::create([
            'entity_type' => 'rate',
            'entity_id' => $adjustment->id,
            'field_name' => 'adjustment.removed',
            'old_value' => "{$adjustment->adjustment_type} {$adjustment->adjustment_value}",
            'new_value' => 'removed',
            'reason' => 'Rate adjustment override removed',
            'changed_by_user_id' => auth()->id(),
        ]);

        $adjustment->delete();
        $this->rates->reapplyAdjustments();

        return back()->with('success', 'Override removed.');
    }
}
