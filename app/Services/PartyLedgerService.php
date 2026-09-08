<?php

namespace App\Services;

use App\Models\Invoice;
use App\Models\Party;
use App\Models\Transaction;
use Illuminate\Support\Collection;

/**
 * Builds a running ledger for a party by summing its dealings by direction.
 *
 * Sources, kept deliberately non-overlapping:
 *   - Invoices (a sale): OUT, amount = grand_total. The invoice already nets
 *     discount and any old-gold exchange, so it is the source of truth for a sale.
 *   - Transactions with no invoice_id (purchases, buy-backs, karigar issues/returns,
 *     shop transfers): direction as stored, amount = total_amount.
 *
 * Exchange/sale transactions carry an invoice_id and are therefore skipped here so
 * their value is not counted twice.
 *
 * Balance convention: positive => receivable (party owes the shop); an IN dealing
 * (we bought from / owe the party) reduces it and may drive it negative (payable).
 */
class PartyLedgerService
{
    public function build(Party $party): array
    {
        $entries = $this->collectEntries($party);

        $totalOut = 0.0;
        $totalIn = 0.0;
        $running = 0.0;

        $rows = $entries->map(function (array $entry) use (&$totalOut, &$totalIn, &$running) {
            $isOut = $entry['direction'] === 'OUT';

            if ($isOut) {
                $totalOut += $entry['amount'];
                $running += $entry['amount'];
            } else {
                $totalIn += $entry['amount'];
                $running -= $entry['amount'];
            }

            return [
                'date'        => $entry['date'],
                'type'        => $entry['type'],
                'direction'   => $entry['direction'],
                'description' => $entry['description'],
                'reference'   => $entry['reference'],
                'debit'       => $isOut ? round($entry['amount'], 2) : 0.0,
                'credit'      => $isOut ? 0.0 : round($entry['amount'], 2),
                'balance'     => round($running, 2),
            ];
        });

        $balance = round($totalOut - $totalIn, 2);

        return [
            'total_out'     => round($totalOut, 2),
            'total_in'      => round($totalIn, 2),
            'balance'       => $balance,
            'balance_label' => $balance > 0
                ? 'Receivable (party owes shop)'
                : ($balance < 0 ? 'Payable (shop owes party)' : 'Settled'),
            'entry_count'   => $rows->count(),
            'entries'       => $rows->all(),
        ];
    }

    private function collectEntries(Party $party): Collection
    {
        $entries = collect();

        Invoice::where('party_id', $party->id)->get()->each(function (Invoice $invoice) use ($entries) {
            $entries->push([
                'ts'          => $invoice->invoice_date?->timestamp ?? 0,
                'src'         => 0,
                'seq'         => $invoice->id,
                'date'        => optional($invoice->invoice_date)->toDateString(),
                'type'        => 'sale',
                'direction'   => 'OUT',
                'description' => 'Sale invoice',
                'reference'   => $invoice->invoice_number,
                'amount'      => (float) $invoice->grand_total,
            ]);
        });

        Transaction::with(['transactionType', 'item'])
            ->where('party_id', $party->id)
            ->whereNull('invoice_id')
            ->get()
            ->each(function (Transaction $txn) use ($entries) {
                $type = $txn->transactionType?->name ?? 'transaction';

                $entries->push([
                    'ts'          => $txn->transaction_date?->timestamp ?? 0,
                    'src'         => 1,
                    'seq'         => $txn->id,
                    'date'        => optional($txn->transaction_date)->toDateString(),
                    'type'        => $type,
                    'direction'   => $txn->direction,
                    'description' => str($type)->replace('_', ' ')->title()->toString(),
                    'reference'   => $txn->item?->item_code ?? ('TXN-' . $txn->id),
                    'amount'      => (float) $txn->total_amount,
                ]);
            });

        return $entries->sortBy([
            ['ts', 'asc'],
            ['src', 'asc'],
            ['seq', 'asc'],
        ])->values();
    }
}
