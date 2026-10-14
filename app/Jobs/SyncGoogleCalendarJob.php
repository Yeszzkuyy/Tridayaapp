<?php

namespace App\Jobs;

use App\Services\GoogleCalendarService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class SyncGoogleCalendarJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function handle(GoogleCalendarService $google): void
    {
        try {
            $google->syncFromGoogle();
        } catch (\Throwable $e) {
            // Auto-sync best-effort: jangan gagalkan job / worker.
            try {
                Log::warning('SyncGoogleCalendarJob gagal: '.$e->getMessage());
            } catch (\Throwable) {
            }
        } finally {
            $google->forgetReachableCache();
        }
    }
}
