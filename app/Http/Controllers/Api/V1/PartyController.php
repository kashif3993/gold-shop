<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Party;
use App\Services\PartyLedgerService;
use Illuminate\Http\JsonResponse;

class PartyController extends Controller
{
    /**
     * GET /api/v1/parties/{party}/ledger
     *
     * Returns the party's running ledger with dealings summed by direction.
     */
    public function ledger(Party $party, PartyLedgerService $ledgerService): JsonResponse
    {
        return response()->json([
            'party'  => $party->load('partyType'),
            'ledger' => $ledgerService->build($party),
        ]);
    }
}
