<?php

namespace App\Jobs;

use App\Models\Setting;
use App\Services\GoldRateService;
use Illuminate\Support\Facades\Log;

/**
 * Scheduled every 15 minutes (see routes/console.php) to keep the gold/silver
 * rate current even when no shopkeeper screen is open to trigger
 * GoldRateService::autoRefreshIfDue() via the rate ticker. Runs synchronously
 * inside the scheduler — it deliberately does not implement ShouldQueue, since
 * that would need a separate `queue:work` process running to ever fire.
 *
 * Respects the same Admin → Settings "Rate Auto-Refresh" toggle the ticker
 * path checks (see GoldRateService::autoRefreshIfDue()) — previously this
 * called refreshFromApi() unconditionally, so turning the toggle off did
 * nothing to a shop running the scheduler: the rate kept auto-updating every
 * 15 minutes regardless. The toggle now genuinely means "no automatic
 * fetching at all," not just "no ticker-triggered fetching."
 */
class FetchGoldRatesJob
{
    public function handle(GoldRateService $rates): void
    {
        if (! Setting::bool('rate_auto_refresh_enabled', true)) {
            return;
        }

        $log = $rates->refreshFromApi();

        if (! $log->success) {
            Log::warning('Scheduled gold rate fetch failed — kept the last cached rate.', [
                'summary' => $log->response_summary,
            ]);
        }
    }
}
