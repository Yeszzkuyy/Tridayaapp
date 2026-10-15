<?php

namespace App\Models;

use App\Events\NotificationCreated;
use Illuminate\Notifications\DatabaseNotification as BaseDatabaseNotification;

/**
 * Model notifikasi database milik app.
 *
 * Setiap baris yang dibuat (via channel `database`) langsung memicu event
 * realtime `NotificationCreated` — bell Notification Center ter-update
 * seketika lewat Laravel Reverb, tanpa menunggu polling.
 */
class DatabaseNotification extends BaseDatabaseNotification
{
    protected static function booted(): void
    {
        static::created(function (self $notification): void {
            NotificationCreated::dispatch($notification);
        });
    }
}
