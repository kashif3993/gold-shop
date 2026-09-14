<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Pull the spot rate through the day; the shop can still "Refresh" manually.
// Only fires while a real server cron runs `php artisan schedule:run` every
// minute — GoldRateService::autoRefreshIfDue() (triggered by the rate ticker
// on any open screen) covers the same 15-minute cadence without needing one.
Schedule::command('rates:fetch')
    ->everyFifteenMinutes()
    ->between('8:00', '21:00')
    ->withoutOverlapping();
