<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Proses antrian (partisi auto area besar) di background.
// cPanel: cukup 1 cron tiap menit menjalankan scheduler:
//   * * * * * cd /home/USER/app && php artisan schedule:run >> /dev/null 2>&1
Schedule::command('queue:work --stop-when-empty --max-time=55')
    ->everyMinute()
    ->withoutOverlapping();
