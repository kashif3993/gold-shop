<?php

namespace App\Jobs;

use App\Services\GoldRateService;
use Illuminate\Support\Facades\Log;

/**
 * Scheduled every 15 minutes (see routes/console.php) to keep the gold/silver
 * rate current even when no shopkeeper screen is open to trigger
 * GoldRateService::autoRefreshIfDue() via the rate ticker. Runs synchronously
 * inside the scheduler — it deliberately does not implement ShouldQueue, since
 * that would need a separate `queue:work` process running to ever fire.
 */
class FetchGoldRatesJob
{
    public function handle(GoldRateService $rates): void
    {
        $log = $rates->refreshFromApi();

        if (! $log->success) {
            Log::warning('Scheduled gold rate fetch failed — kept the last cached rate.', [
                'summary' => $log->response_summary,
            ]);
        }
    }
}
