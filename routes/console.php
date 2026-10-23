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

// Dokumen di Trash dihapus permanen setelah 14 hari (dihitung dari deleted_at).
Schedule::command('documents:purge-trash')->hourly();

// Sinkron Google Calendar otomatis tiap 5 menit (idempotent, tidak duplikat).
Schedule::command('gcal:sync')->everyFiveMinutes()->withoutOverlapping();

// ponytail: worker ringan tiap menit agar notifikasi antre (mis. NewLeadNotification) terkirim
// tanpa proses supervisor; database driver atomic sehingga overlap antar worker aman
Schedule::command('queue:work --stop-when-empty --max-time=50')->everyMinute();
