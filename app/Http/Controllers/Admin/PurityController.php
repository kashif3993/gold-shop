<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\MetalType;
use App\Models\Purity;
use App\Services\AuditLogger;
use Illuminate\Http\Request;
use Inertia\Inertia;

class PurityController extends Controller
{
    public function __construct(private AuditLogger $audit) {}

    public function index()
    {
        return Inertia::render('admin/purities/index', [
            'metals' => MetalType::with(['purities' => fn ($q) => $q->orderByDesc('fineness_percent')])->orderBy('name')->get(),
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'metal_type_id' => 'required|exists:metal_types,id',
            'name' => 'required|string|max:40',
            'fineness_percent' => 'required|numeric|min:0.01|max:100',
        ]);

        $purity = Purity::create($data + ['is_active' => true]);

        $this->audit->log('purity', $purity->id, 'created', null, "{$purity->name} ({$purity->fineness_percent}%)", 'New purity added', auth()->id());

        return back()->with('success', "Purity \"{$purity->name}\" added.");
    }

    public function update(Request $request, Purity $purity)
    {
        $data = $request->validate([
            'name' => 'required|string|max:40',
            'fineness_percent' => 'required|numeric|min:0.01|max:100',
            'reason' => 'nullable|string|max:255',
        ]);

        $old = "{$purity->name} ({$purity->fineness_percent}%)";

        $purity->update([
            'name' => $data['name'],
            'fineness_percent' => $data['fineness_percent'],
        ]);

        $this->audit->logIfChanged('purity', $purity->id, 'details', $old, "{$purity->name} ({$purity->fineness_percent}%)", $data['reason'] ?: 'Purity details updated', auth()->id());

        return back()->with('success', 'Purity updated.');
    }

    /** Flip active/inactive — used instead of delete, since purities are referenced by items/transactions/rates. */
    public function toggle(Purity $purity)
    {
        $old = $purity->is_active ? 'active' : 'inactive';
        $purity->update(['is_active' => ! $purity->is_active]);
        $new = $purity->is_active ? 'active' : 'inactive';

        $this->audit->log('purity', $purity->id, 'is_active', $old, $new, $purity->is_active ? 'Purity reactivated' : 'Purity deactivated', auth()->id());

        return back()->with('success', $purity->is_active ? "\"{$purity->name}\" reactivated." : "\"{$purity->name}\" deactivated.");
    }
}
