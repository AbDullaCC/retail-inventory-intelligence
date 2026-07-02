<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Nightly forecast refresh (needs the Apps/Forecast sidecar running). On dev
// there is no cron — run `php artisan forecast:run` manually or `schedule:work`.
Schedule::command('forecast:run')->dailyAt('06:00');
