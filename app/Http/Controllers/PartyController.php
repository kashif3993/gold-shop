<?php

namespace App\Http\Controllers;

use App\Http\Requests\StorePartyRequest;
use App\Http\Requests\UpdatePartyRequest;
use App\Models\Party;
use App\Models\PartyType;
use App\Services\PartyLedgerService;
use Illuminate\Http\Request;
use Inertia\Inertia;

class PartyController extends Controller
{
    public function index(Request $request)
    {
        $query = Party::with('partyType')
            ->withCount(['transactions', 'invoices', 'sourcedItems'])
            ->orderBy('name');

        if ($request->filled('party_type_id')) {
            $query->where('party_type_id', $request->party_type_id);
        }

        if ($request->filled('status')) {
            $query->where('is_active', $request->status === 'active');
        }

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('phone', 'like', "%{$search}%");
            });
        }

        return Inertia::render('parties/index', [
            'parties'    => $query->paginate(20)->withQueryString(),
            'partyTypes' => PartyType::orderBy('id')->get(),
            'filters'    => $request->only(['party_type_id', 'status', 'search']),
        ]);
    }

    public function create()
    {
        return Inertia::render('parties/create', [
            'partyTypes' => PartyType::orderBy('id')->get(),
        ]);
    }

    public function store(StorePartyRequest $request)
    {
        $party = Party::create($request->validated());

        return redirect()->route('parties.index')
            ->with('success', "Party \"{$party->name}\" created.");
    }

    public function show(Party $party, PartyLedgerService $ledgerService)
    {
        $party->loadCount(['sourcedItems']);

        return Inertia::render('parties/show', [
            'party'        => $party->load('partyType'),
            'ledger'       => $ledgerService->build($party),
            'sourcedItems' => $party->sourcedItems()
                ->with(['metalType', 'purity'])
                ->latest('id')
                ->limit(10)
                ->get(),
        ]);
    }

    public function edit(Party $party)
    {
        return Inertia::render('parties/edit', [
            'party'      => $party->load('partyType'),
            'partyTypes' => PartyType::orderBy('id')->get(),
        ]);
    }

    public function update(UpdatePartyRequest $request, Party $party)
    {
        $party->update($request->validated());

        return redirect()->route('parties.show', $party)
            ->with('success', "Party \"{$party->name}\" updated.");
    }

    public function destroy(Party $party)
    {
        $hasLinkedRecords = $party->transactions()->exists()
            || $party->invoices()->exists()
            || $party->sourcedItems()->exists();

        if ($hasLinkedRecords) {
            return back()->with('error', 'This party has linked transactions, invoices, or items and cannot be deleted. Mark it inactive instead.');
        }

        $party->delete();

        return redirect()->route('parties.index')
            ->with('success', 'Party deleted.');
    }
}
