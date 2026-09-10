<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Pull the spot rate through the day; the shop can still "Refresh" manually.
Schedule::command('rates:fetch')
    ->hourly()
    ->between('8:00', '21:00')
    ->withoutOverlapping();
