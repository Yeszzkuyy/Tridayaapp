<?php

namespace App\Console\Commands;

use App\Services\GoogleCalendarService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class SyncGoogleCalendar extends Command
{
    protected $signature = 'gcal:sync';

    protected $description = 'Tarik event Google Calendar ke jadwal teknisi secara otomatis.';

    public function handle(GoogleCalendarService $google): int
    {
        // Lock anti-bentrok kini terpusat di syncTwoWay() (dipakai juga oleh
        // job & tombol manual), sehingga semua jalur saling eksklusif.
        try {
            $stats = $google->syncTwoWay();

            if (! empty($stats['locked'])) {
                return self::SUCCESS; // sync lain sedang berjalan
            }

            Log::info('gcal:sync selesai', $stats);
            $this->info(sprintf(
                'Sinkron selesai: %d baru, %d diperbarui, %d dipulihkan, %d dilewati.',
                $stats['created'] ?? 0,
                $stats['updated'] ?? 0,
                $stats['restored'] ?? 0,
                $stats['skipped'] ?? 0,
            ));
        } catch (\Throwable $e) {
            // Jangan biarkan kegagalan Google menggagalkan scheduler.
            try {
                Log::warning('gcal:sync gagal: '.$e->getMessage());
            } catch (\Throwable) {
            }
            $this->warn('Sinkron Google gagal: '.$e->getMessage());
        } finally {
            $google->forgetReachableCache();
        }

        return self::SUCCESS;
    }
}
