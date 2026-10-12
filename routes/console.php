<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('schedules:remind')->everyMinute();
Schedule::command('followups:remind')->daily();
Schedule::command('proposals:expire')->dailyAt('01:30');

// ponytail: worker ringan tiap menit agar notifikasi antre (mis. NewLeadNotification) terkirim
// tanpa proses supervisor; database driver atomic sehingga overlap antar worker aman
Schedule::command('queue:work --stop-when-empty --max-time=50')->everyMinute();
