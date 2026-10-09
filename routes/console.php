<?php

use App\Jobs\FetchGoldRatesJob;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Pull the spot rate through the day; the shop can still "Refresh" manually.
// The actual work lives in app/Jobs/FetchGoldRatesJob.php — this is only the
// schedule registration, same as this file registers Artisan commands above.
// Only fires while a real server cron runs `php artisan schedule:run` every
// minute — GoldRateService::autoRefreshIfDue() (triggered by the rate ticker
// on any open screen) covers the same 15-minute cadence without needing one.
Schedule::job(new FetchGoldRatesJob)
    ->everyFifteenMinutes()
    ->between('8:00', '21:00')
    ->withoutOverlapping();

// Nightly database backup — a plain .sql file (see BackupDatabaseRaw), not a
// zip, written to local disk(s) only (config/filesystems.php), no internet or
// cloud step involved, so it runs the same whether the shop is online or not.
// Pruning old dumps past the retention window happens in the same command.
// Like the rate fetch above, this only fires while a real server cron runs
// `php artisan schedule:run` every minute.
Schedule::command('backup:run-raw')
    ->dailyAt('23:30')
    ->withoutOverlapping();
