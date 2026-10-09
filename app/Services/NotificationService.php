<?php

namespace App\Services;

use App\Models\BankQrPayment;
use App\Models\Purity;
use App\Models\User;
use Illuminate\Support\Collection;

/**
 * The header bell's content — computed fresh on every call, never stored.
 * Each of these three is a live state the app already tracks elsewhere
 * (pending Bank QR payments, current stock, the rate feed's own staleness
 * flag), so there's nothing to persist, mark read, or clean up: a
 * notification simply stops appearing once its underlying condition does.
 */
class NotificationService
{
    public function __construct(private GoldRateService $rates) {}

    /** @return array<int, array{id: string, title: string, message: string, severity: string, href: string|null}> */
    public function for(User $user): array
    {
        $notifications = [];

        if ($user->canConfirmBankQr()) {
            $pending = BankQrPayment::where('status', 'pending')->where('expires_at', '>', now())->count();
            if ($pending > 0) {
                $notifications[] = [
                    'id' => 'bank-qr-pending',
                    'title' => 'Bank QR payments awaiting confirmation',
                    'message' => $pending === 1
                        ? '1 payment is waiting for you to confirm the transfer arrived.'
                        : "{$pending} payments are waiting for you to confirm the transfer arrived.",
                    'severity' => 'warning',
                    'href' => '/pos',
                ];
            }
        }

        foreach ($this->outOfStockPurities() as $purity) {
            $notifications[] = [
                'id' => "out-of-stock-{$purity->id}",
                'title' => 'Out of stock',
                'message' => "No {$purity->metalType->name} {$purity->name} items currently in stock.",
                'severity' => 'info',
                'href' => '/inventory',
            ];
        }

        if ($this->rates->isFeedStale()) {
            $notifications[] = [
                'id' => 'rate-feed-stale',
                'title' => 'Gold/Silver rate feed is stale',
                'message' => "The live rate hasn't refreshed in over {$this->rates->staleAfterHours()} hours — check Rate Management.",
                'severity' => 'danger',
                'href' => '/rate-management',
            ];
        }

        return $notifications;
    }

    /**
     * A purity the shop has actually stocked before (at least one Item ever
     * created for it) but has zero "in_stock" right now — a purity nobody
     * has ever carried isn't "out of stock", it's just unused, so that case
     * is deliberately excluded to avoid noise.
     *
     * @return Collection<int, Purity>
     */
    private function outOfStockPurities()
    {
        return Purity::with('metalType:id,name')
            ->where('is_active', true)
            ->whereHas('metalType', fn ($q) => $q->where('is_active', true))
            ->whereHas('items')
            ->whereDoesntHave('items', fn ($q) => $q->where('status', 'in_stock'))
            ->get();
    }
}
