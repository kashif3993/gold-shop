<?php

namespace App\Console\Commands;

use App\Services\GoldRateService;
use Illuminate\Console\Command;

class FetchGoldRates extends Command
{
    protected $signature = 'rates:fetch';

    protected $description = 'Fetch the current gold/silver spot rate, apply the shop adjustment, and store it';

    public function handle(GoldRateService $rates): int
    {
        $log = $rates->refreshFromApi();

        $this->line($log->response_summary);

        if ($log->success) {
            $this->info('Rate fetch succeeded.');

            return self::SUCCESS;
        }

        $this->warn('Rate fetch failed — kept the last cached rate.');

        return self::FAILURE;
    }
}
