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
        // Lock anti-bentrok bila scheduler & auto-sync-on-fetch berbarengan.
        $lock = Cache::lock('gcal:sync', 300);

        if (! $lock->get()) {
            return self::SUCCESS;
        }

        try {
            $stats = $google->syncFromGoogle();

            Log::info('gcal:sync selesai', $stats);
            $this->info(sprintf(
                'Sinkron selesai: %d baru, %d diperbarui, %d dipulihkan, %d dilewati.',
                $stats['created'],
                $stats['updated'],
                $stats['restored'],
                $stats['skipped'],
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
            $lock->release();
        }

        return self::SUCCESS;
    }
}
